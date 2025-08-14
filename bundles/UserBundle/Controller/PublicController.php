<?php

namespace Mautic\UserBundle\Controller;

use Mautic\CoreBundle\Controller\FormController;
use Mautic\UserBundle\Entity\User;
use Mautic\UserBundle\Form\Type\PasswordResetConfirmType;
use Mautic\UserBundle\Form\Type\PasswordResetType;
use Mautic\UserBundle\Form\Type\UserInviteRegistrationType;
use Mautic\UserBundle\Model\RoleModel;
use Mautic\UserBundle\Model\UserModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class PublicController extends FormController
{
    /**
     * Generates a new password for the user and emails it to them.
     */
    public function passwordResetAction(Request $request): \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
    {
        /** @var UserModel $model */
        $model = $this->getModel('user');

        $data   = ['identifier' => ''];
        $action = $this->generateUrl('mautic_user_passwordreset');
        $form   = $this->formFactory->create(PasswordResetType::class, $data, ['action' => $action]);

        // /Check for a submitted form and process it
        if ('POST' === $request->getMethod()) {
            if ($isValid = $this->isFormValid($form)) {
                // find the user
                $data = $form->getData();
                $user = $model->getRepository()->findByIdentifier($data['identifier']);

                /**
                 * Calculation of time to standardize fix response for vulnerability
                 * Users enumeration - forgot password. Constant response time is 1s.
                 */
                $desiredTime = 1.0;
                $startTime   = microtime(true);

                try {
                    if (null !== $user) {
                        $model->sendResetEmail($user);
                    }
                    $this->addFlashMessage('mautic.user.user.notice.passwordreset');
                } catch (\Exception) {
                    $this->addFlashMessage('mautic.user.user.notice.passwordreset.error', [], 'error');
                }

                $endTime       = microtime(true);
                $executionTime = $endTime - $startTime;

                if ($executionTime < $desiredTime) {
                    usleep((int) (($desiredTime - $executionTime) * 1000000));
                }

                return $this->redirectToRoute('login');
            }
        }

        return $this->delegateView([
            'viewParameters' => [
                'form' => $form->createView(),
            ],
            'contentTemplate' => '@MauticUser/Security/reset.html.twig',
            'passthroughVars' => [
                'route' => $action,
            ],
        ]);
    }

    public function passwordResetConfirmAction(Request $request, UserPasswordHasherInterface $hasher): mixed
    {
        /** @var UserModel $model */
        $model = $this->getModel('user');

        $data   = ['identifier' => '', 'password' => '', 'password_confirm' => ''];
        $action = $this->generateUrl('mautic_user_passwordresetconfirm');
        $form   = $this->formFactory->create(PasswordResetConfirmType::class, [], ['action' => $action]);
        $token  = $request->query->get('token');

        if ($token) {
            $request->getSession()->set('resetToken', $token);
        }

        // /Check for a submitted form and process it
        if ('POST' === $request->getMethod()) {
            if ($isValid = $this->isFormValid($form)) {
                // find the user
                $data = $form->getData();
                /** @var User $user */
                $user = $model->getRepository()->findByIdentifier($data['identifier']);

                if (null == $user) {
                    $this->addFlashMessage('mautic.user.user.notice.passwordreset.success');

                    return $this->redirectToRoute('login');
                } else {
                    if ($request->getSession()->has('resetToken')) {
                        $resetToken = $request->getSession()->get('resetToken');

                        if ($model->confirmResetToken($user, $resetToken)) {
                            $encodedPassword = $model->checkNewPassword($user, $hasher, $data['plainPassword']);
                            $user->setPassword($encodedPassword);
                            $model->saveEntity($user);

                            $this->addFlashMessage('mautic.user.user.notice.passwordreset.success');

                            $request->getSession()->remove('resetToken');

                            return $this->redirectToRoute('login');
                        }

                        return $this->delegateView([
                            'viewParameters' => [
                                'form' => $form->createView(),
                            ],
                            'contentTemplate' => '@MauticUser/Security/resetconfirm.html.twig',
                            'passthroughVars' => [
                                'route' => $action,
                            ],
                        ]);
                    } else {
                        $this->addFlashMessage('mautic.user.user.notice.passwordreset.missingtoken');

                        return $this->redirectToRoute('mautic_user_passwordresetconfirm');
                    }
                }
            }
        }

        return $this->delegateView([
            'viewParameters' => [
                'form' => $form->createView(),
            ],
            'contentTemplate' => '@MauticUser/Security/resetconfirm.html.twig',
            'passthroughVars' => [
                'route' => $action,
            ],
        ]);
    }

    public function inviteAction(Request $request, UserPasswordHasherInterface $hasher): mixed
    {
        /** @var UserModel $model */
        $model = $this->getModel('user');

        $token  = $request->get('token');
        $invite = $model->getInvite($token);
        if (null === $invite) {
            $this->addFlashMessage('mautic.user.invite.invalid', [], 'error', 'flashes');

            return $this->redirectToRoute('login');
        }

        $user = new User();
        $user->setEmail($invite->getEmail());

        if ($invite->getRole()) {
            $user->setRole($invite->getRole());
        } else {
            /** @var RoleModel $roleModel */
            $roleModel = $this->getModel('user.role');
            $role      = $roleModel->getRepository()->findOneBy([], ['id' => 'ASC']);
            if (null !== $role) {
                $user->setRole($role);
            }
        }

        $action = $this->generateUrl('mautic_user_invite_register', ['token' => $token]);
        $form   = $this->formFactory->create(UserInviteRegistrationType::class, [], [
            'action' => $action,
        ]);

        if ('POST' === $request->getMethod()) {
            $form->handleRequest($request);

            // Check if user already exists before form validation
            $existingUser = $model->getRepository()->findOneBy(['email' => $invite->getEmail()]);
            if ($existingUser) {
                $this->addFlashMessage('mautic.user.invite.error.email_exists', [], 'error', 'flashes');

                return $this->delegateView([
                    'viewParameters' => [
                        'form' => $form->createView(),
                    ],
                    'contentTemplate' => '@MauticUser/Security/register.html.twig',
                    'passthroughVars' => [
                        'route' => $action,
                    ],
                ]);
            }

            if ($form->isSubmitted() && $form->isValid()) {
                try {
                    $formData = $form->getData();

                    // Create user from form data
                    $user = new User();
                    $user->setUsername($formData['username']);
                    $user->setFirstName($formData['firstName']);
                    $user->setLastName($formData['lastName']);
                    $user->setEmail($invite->getEmail());
                    $user->setPlainPassword($formData['plainPassword']);

                    if ($invite->getRole()) {
                        $user->setRole($invite->getRole());
                    } else {
                        /** @var RoleModel $roleModel */
                        $roleModel = $this->getModel('user.role');
                        $role      = $roleModel->getRepository()->findOneBy([], ['id' => 'ASC']);
                        if (null !== $role) {
                            $user->setRole($role);
                        }
                    }

                    $encoded = $model->checkNewPassword($user, $hasher, $user->getPlainPassword());
                    $user->setPassword($encoded);

                    $model->saveEntity($user);
                    $model->markInviteUsed($invite);
                    $this->addFlashMessage('mautic.user.invite.account_created', [], 'notice', 'flashes');

                    return $this->redirectToRoute('login');
                } catch (\Exception) {
                    $this->addFlashMessage('mautic.user.invite.error.generic', [], 'error', 'flashes');
                }
            }
        }

        return $this->delegateView([
            'viewParameters' => [
                'form' => $form->createView(),
            ],
            'contentTemplate' => '@MauticUser/Security/register.html.twig',
            'passthroughVars' => [
                'route' => $action,
            ],
        ]);
    }
}
