<?php

declare(strict_types=1);

namespace Mautic\DashboardBundle\Tests\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Mautic\CoreBundle\Factory\ModelFactory;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Service\FlashBag;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\DashboardBundle\Controller\DashboardController;
use Mautic\DashboardBundle\Dashboard\Widget;
use Mautic\DashboardBundle\Model\DashboardModel;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

class DashboardControllerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|Request
     */
    private $requestMock;

    /**
     * @var MockObject|CorePermissions
     */
    private MockObject $securityMock;

    /**
     * @var MockObject|Translator
     */
    private MockObject $translatorMock;

    /**
     * @var MockObject|ModelFactory<DashboardModel>
     */
    private MockObject $modelFactoryMock;

    /**
     * @var MockObject|DashboardModel
     */
    private MockObject $dashboardModelMock;

    /**
     * @var MockObject|RouterInterface
     */
    private MockObject $routerMock;

    /**
     * @var MockObject&FlashBag
     */
    private MockObject $flashBagMock;

    /**
     * @var MockObject|Container
     */
    private MockObject $containerMock;

    private DashboardController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requestMock        = $this->createMock(Request::class);
        $this->dashboardModelMock = $this->createMock(DashboardModel::class);
        $this->routerMock         = $this->createMock(RouterInterface::class);
        $this->containerMock      = $this->createMock(Container::class);

        $doctrine                 = $this->createMock(ManagerRegistry::class);
        $this->modelFactoryMock   = $this->createMock(ModelFactory::class);
        $userHelper               = $this->createMock(UserHelper::class);
        $coreParametersHelper     = $this->createMock(CoreParametersHelper::class);
        $dispatcher               = $this->createMock(EventDispatcherInterface::class);
        $this->translatorMock     = $this->createMock(Translator::class);
        $this->flashBagMock       = $this->createMock(FlashBag::class);
        $requestStack             = new RequestStack();
        $this->securityMock       = $this->createMock(CorePermissions::class);

        $this->setupRequestMock();

        $requestStack->push($this->requestMock);
        $this->controller = new DashboardController(
            $doctrine,
            $this->modelFactoryMock,
            $userHelper,
            $coreParametersHelper,
            $dispatcher,
            $this->translatorMock,
            $this->flashBagMock,
            $requestStack,
            $this->securityMock
        );
        $this->controller->setContainer($this->containerMock);
    }

    private function setupRequestMock(): void
    {
        $this->requestMock->request = new InputBag();
        $this->requestMock->query   = new InputBag();
    }

    private function setupRequestMockForForm(): void
    {
        $this->requestMock->request = new InputBag(['widget' => ['buttons' => ['save' => 'Save']]]);
        $this->requestMock->query   = new InputBag();
    }

    public function testSaveWithGetWillCallAccessDenied(): void
    {
        $this->requestMock->expects($this->once())
            ->method('isMethod')
            ->willReturn(true);

        $this->requestMock->expects(self::once())
            ->method('isXmlHttpRequest')
            ->willReturn(false);

        $this->expectException(AccessDeniedHttpException::class);
        $this->controller->saveAction($this->requestMock);
    }

    public function testSaveWithPostNotAjaxWillCallAccessDenied(): void
    {
        $this->requestMock->expects($this->once())
            ->method('isMethod')
            ->willReturn(true);

        $this->requestMock->method('isXmlHttpRequest')
            ->willReturn(false);

        $this->translatorMock->expects($this->once())
            ->method('trans')
            ->with('mautic.core.url.error.401');

        $this->expectException(AccessDeniedHttpException::class);
        $this->controller->saveAction($this->requestMock);
    }

    public function testSaveWithPostAjaxWillSave(): void
    {
        $this->requestMock->expects($this->once())
            ->method('isMethod')
            ->willReturn(true);

        $this->requestMock->method('isXmlHttpRequest')->willReturn(true);
        $this->requestMock->method('get')->willReturn('mockName');

        $this->containerMock->expects($this->exactly(2))
            ->method('get')->willReturnCallback(function (...$parameters) {
                $this->assertSame('router', $parameters[0]);

                return $this->routerMock;
            });

        $this->routerMock->expects($this->any())
            ->method('generate')
            ->willReturn('https://some.url');

        $this->modelFactoryMock->expects($this->once())
            ->method('getModel')
            ->with('dashboard')
            ->willReturn($this->dashboardModelMock);

        $this->dashboardModelMock->expects($this->once())
            ->method('saveSnapshot')
            ->with('mockName');

        $this->translatorMock->expects($this->once())
            ->method('trans')
            ->with('mautic.dashboard.notice.save');

        $this->controller->saveAction($this->requestMock);
    }

    public function testSaveWithPostAjaxWillNotBeAbleToSave(): void
    {
        $this->requestMock->expects($this->once())
            ->method('isMethod')
            ->willReturn(true);

        $this->requestMock->method('isXmlHttpRequest')
            ->willReturn(true);

        $this->routerMock->expects($this->any())
            ->method('generate')
            ->willReturn('https://some.url');

        $this->requestMock->method('get')->willReturn('mockName');

        $this->containerMock->expects($this->once())
            ->method('get')
            ->with('router')
            ->willReturn($this->routerMock);

        $this->modelFactoryMock->expects($this->once())
            ->method('getModel')
            ->with('dashboard')
            ->willReturn($this->dashboardModelMock);

        $this->dashboardModelMock->expects($this->once())
            ->method('saveSnapshot')
            ->will($this->throwException(new IOException('some error message')));

        $this->translatorMock->expects($this->once())
            ->method('trans')
            ->with('mautic.dashboard.error.save');

        $this->controller->saveAction($this->requestMock);
    }

    public function testWidgetDirectRequest(): void
    {
        $this->requestMock->method('isXmlHttpRequest')
            ->willReturn(false);

        $this->expectException(NotFoundHttpException::class);
        $this->controller->widgetAction($this->requestMock, $this->createMock(Widget::class), $this->createMock(Environment::class), 1);
    }

    public function testWidgetNotFound(): void
    {
        $widgetId = '1';
        $twig     = $this->createMock(Environment::class);

        $this->requestMock->method('isXmlHttpRequest')
            ->willReturn(true);

        $widgetService = $this->createMock(Widget::class);
        $widgetService->expects(self::once())
            ->method('setFilter')
            ->with($this->requestMock);
        $widgetService->expects(self::once())
            ->method('get')
            ->with((int) $widgetId)
            ->willReturn(null);

        $this->containerMock->expects(self::never())
            ->method('get');

        $this->expectException(NotFoundHttpException::class);
        $this->controller->widgetAction($this->requestMock, $widgetService, $twig, $widgetId);
    }

    public function testWidget(): void
    {
        $widgetId        = '1';
        $widget          = new \Mautic\DashboardBundle\Entity\Widget();
        $renderedContent = 'lfsadkdhfůasfjds';
        $twig            = $this->createMock(Environment::class);

        $twig->expects(self::once())
            ->method('render')
            ->willReturn($renderedContent);

        $this->requestMock->method('isXmlHttpRequest')
            ->willReturn(true);

        $widgetService = $this->createMock(Widget::class);
        $widgetService->expects(self::once())
            ->method('setFilter')
            ->with($this->requestMock);
        $widgetService->expects(self::once())
            ->method('get')
            ->with((int) $widgetId)
            ->willReturn($widget);

        $response = $this->controller->widgetAction($this->requestMock, $widgetService, $twig, $widgetId);

        self::assertSame('{"success":1,"widgetId":"1","widgetHtml":"lfsadkdhf\u016fasfjds","widgetWidth":null,"widgetHeight":null}', $response->getContent());
    }

    public function testNewActionWithGetRequest(): void
    {
        $this->requestMock->expects($this->once())
            ->method('isMethod')
            ->with(Request::METHOD_POST)
            ->willReturn(false);

        $this->containerMock->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(function (...$parameters) {
                if ($parameters[0] === 'router') {
                    return $this->routerMock;
                }
                if ($parameters[0] === 'form.factory') {
                    return $this->createMock(FormFactoryInterface::class);
                }
                if ($parameters[0] === 'twig') {
                    $twigMock = $this->createMock(Environment::class);
                    $twigMock->expects($this->once())
                        ->method('render')
                        ->willReturn('<div>Flash messages</div>');
                    return $twigMock;
                }
                return null;
            });

        $this->containerMock->expects($this->atLeastOnce())
            ->method('has')
            ->with('twig')
            ->willReturn(true);

        $this->routerMock->expects($this->once())
            ->method('generate')
            ->with('mautic_dashboard_action', ['objectAction' => 'new'])
            ->willReturn('https://some.url');

        $this->modelFactoryMock->expects($this->once())
            ->method('getModel')
            ->with('dashboard')
            ->willReturn($this->dashboardModelMock);

        $this->dashboardModelMock->expects($this->once())
            ->method('createForm')
            ->willReturn($this->createMock(Form::class));

        $response = $this->controller->newAction($this->requestMock, $this->createMock(FormFactoryInterface::class));

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\Response::class, $response);
    }

    public function testNewActionWithPostRequestValidForm(): void
    {
        $this->setupRequestMockForForm();

        $this->requestMock->expects($this->once())
            ->method('isMethod')
            ->with(Request::METHOD_POST)
            ->willReturn(true);

        $this->containerMock->expects($this->atLeastOnce())
            ->method('get')
            ->willReturnCallback(function (...$parameters) {
                if ($parameters[0] === 'router') {
                    return $this->routerMock;
                }
                if ($parameters[0] === 'form.factory') {
                    return $this->createMock(FormFactoryInterface::class);
                }
                if ($parameters[0] === 'twig') {
                    $twigMock = $this->createMock(Environment::class);
                    $twigMock->expects($this->exactly(2))
                        ->method('render')
                        ->willReturn('<div>Widget HTML</div>');
                    return $twigMock;
                }
                return null;
            });

        $this->containerMock->expects($this->atLeastOnce())
            ->method('has')
            ->with('twig')
            ->willReturn(true);

        $this->routerMock->expects($this->once())
            ->method('generate')
            ->with('mautic_dashboard_action', ['objectAction' => 'new'])
            ->willReturn('https://some.url');

        $this->modelFactoryMock->expects($this->once())
            ->method('getModel')
            ->with('dashboard')
            ->willReturn($this->dashboardModelMock);

        $formMock = $this->createMock(Form::class);
        $formMock->expects($this->once())
            ->method('isSubmitted')
            ->willReturn(true);
        $formMock->expects($this->once())
            ->method('isValid')
            ->willReturn(true);
        $formMock->expects($this->once())
            ->method('getName')
            ->willReturn('widget');
        $formMock->expects($this->once())
            ->method('handleRequest')
            ->willReturnSelf();
        $formMock->expects($this->once())
            ->method('get')
            ->with('buttons')
            ->willReturn($this->createMock(Form::class));
        $formMock->expects($this->once())
            ->method('getData')
            ->willReturn(new \Mautic\DashboardBundle\Entity\Widget());

        $this->dashboardModelMock->expects($this->once())
            ->method('createForm')
            ->willReturn($formMock);

        $this->dashboardModelMock->expects($this->once())
            ->method('saveEntity');

        $this->dashboardModelMock->expects($this->once())
            ->method('getDefaultFilter')
            ->willReturn([]);

        $this->dashboardModelMock->expects($this->once())
            ->method('populateWidgetContent');

        $this->flashBagMock->expects($this->once())
            ->method('add')
            ->with('mautic.dashboard.widget.created', [], \Mautic\CoreBundle\Service\FlashBag::LEVEL_NOTICE, 'flashes', false);

        $response = $this->controller->newAction($this->requestMock, $this->createMock(FormFactoryInterface::class));

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\JsonResponse::class, $response);

        $responseData = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('flashes', $responseData);
        $this->assertArrayHasKey('widgetId', $responseData);
        $this->assertArrayHasKey('widgetWidth', $responseData);
        $this->assertArrayHasKey('widgetHeight', $responseData);
        $this->assertArrayHasKey('widgetHtml', $responseData);
        $this->assertArrayHasKey('upWidgetCount', $responseData);
        $this->assertArrayHasKey('closeModal', $responseData);
        $this->assertArrayHasKey('mauticContent', $responseData);
    }

    public function testNewActionWithPostRequestCancelledForm(): void
    {
        $this->setupRequestMockForForm();

        $this->requestMock->setMethod(Request::METHOD_POST);

        $this->containerMock->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(function (...$parameters) {
                if ($parameters[0] === 'router') {
                    return $this->routerMock;
                }
                if ($parameters[0] === 'form.factory') {
                    return $this->createMock(FormFactoryInterface::class);
                }
                return null;
            });

        $this->routerMock->expects($this->once())
            ->method('generate')
            ->with('mautic_dashboard_action', ['objectAction' => 'new'])
            ->willReturn('https://some.url');

        $this->modelFactoryMock->expects($this->once())
            ->method('getModel')
            ->with('dashboard')
            ->willReturn($this->dashboardModelMock);

        $formMock = $this->createMock(Form::class);
        $formMock->expects($this->once())
            ->method('isSubmitted')
            ->willReturn(true);
        $formMock->expects($this->once())
            ->method('isValid')
            ->willReturn(false);
        $formMock->expects($this->once())
            ->method('get')
            ->with('buttons')
            ->willReturn($this->createMock(Form::class));
        $formMock->expects($this->once())
            ->method('getData')
            ->willReturn(new \Mautic\DashboardBundle\Entity\Widget());

        $this->dashboardModelMock->expects($this->once())
            ->method('createForm')
            ->willReturn($formMock);

        $this->dashboardModelMock->expects($this->never())
            ->method('saveEntity');

        $this->flashBagMock->expects($this->never())
            ->method('add');

        $response = $this->controller->newAction($this->requestMock, $this->createMock(FormFactoryInterface::class));

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\JsonResponse::class, $response);

        $responseData = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('flashes', $responseData);
        $this->assertArrayNotHasKey('widgetId', $responseData);
        $this->assertArrayNotHasKey('widgetWidth', $responseData);
        $this->assertArrayNotHasKey('widgetHeight', $responseData);
        $this->assertArrayNotHasKey('widgetHtml', $responseData);
        $this->assertArrayNotHasKey('upWidgetCount', $responseData);
        $this->assertArrayHasKey('closeModal', $responseData);
        $this->assertArrayHasKey('mauticContent', $responseData);
    }

    public function testNewActionWithPostRequestInvalidForm(): void
    {
        $this->setupRequestMockForForm();

        $this->requestMock->setMethod(Request::METHOD_POST);

        $this->containerMock->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(function (...$parameters) {
                if ($parameters[0] === 'router') {
                    return $this->routerMock;
                }
                if ($parameters[0] === 'form.factory') {
                    return $this->createMock(FormFactoryInterface::class);
                }
                return null;
            });

        $this->routerMock->expects($this->once())
            ->method('generate')
            ->with('mautic_dashboard_action', ['objectAction' => 'new'])
            ->willReturn('https://some.url');

        $this->modelFactoryMock->expects($this->once())
            ->method('getModel')
            ->with('dashboard')
            ->willReturn($this->dashboardModelMock);

        $formMock = $this->createMock(Form::class);
        $formMock->expects($this->once())
            ->method('isSubmitted')
            ->willReturn(true);
        $formMock->expects($this->once())
            ->method('isValid')
            ->willReturn(false);
        $formMock->expects($this->once())
            ->method('get')
            ->with('buttons')
            ->willReturn($this->createMock(Form::class));
        $formMock->expects($this->once())
            ->method('getData')
            ->willReturn(new \Mautic\DashboardBundle\Entity\Widget());

        $this->dashboardModelMock->expects($this->once())
            ->method('createForm')
            ->willReturn($formMock);

        $this->dashboardModelMock->expects($this->never())
            ->method('saveEntity');

        $this->flashBagMock->expects($this->never())
            ->method('add');

        $response = $this->controller->newAction($this->requestMock, $this->createMock(FormFactoryInterface::class));

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\JsonResponse::class, $response);

        $responseData = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('flashes', $responseData);
        $this->assertArrayNotHasKey('widgetId', $responseData);
        $this->assertArrayNotHasKey('widgetWidth', $responseData);
        $this->assertArrayNotHasKey('widgetHeight', $responseData);
        $this->assertArrayNotHasKey('widgetHtml', $responseData);
        $this->assertArrayNotHasKey('upWidgetCount', $responseData);
        $this->assertArrayHasKey('closeModal', $responseData);
        $this->assertArrayHasKey('mauticContent', $responseData);
    }

    public function testEditActionWithGetRequest(): void
    {
        $objectId = 1;
        $this->requestMock->expects($this->once())
            ->method('isMethod')
            ->with(Request::METHOD_POST)
            ->willReturn(false);

        $this->containerMock->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(function (...$parameters) {
                if ($parameters[0] === 'router') {
                    return $this->routerMock;
                }
                if ($parameters[0] === 'form.factory') {
                    return $this->createMock(FormFactoryInterface::class);
                }
                return null;
            });

        $this->routerMock->expects($this->once())
            ->method('generate')
            ->with('mautic_dashboard_action', ['objectAction' => 'edit', 'objectId' => $objectId])
            ->willReturn('https://some.url');

        $this->modelFactoryMock->expects($this->once())
            ->method('getModel')
            ->with('dashboard')
            ->willReturn($this->dashboardModelMock);

        $widget = new \Mautic\DashboardBundle\Entity\Widget();
        $this->dashboardModelMock->expects($this->once())
            ->method('getEntity')
            ->with($objectId)
            ->willReturn($widget);

        $this->dashboardModelMock->expects($this->once())
            ->method('createForm')
            ->willReturn($this->createMock(Form::class));

        $response = $this->controller->editAction($this->requestMock, $this->createMock(FormFactoryInterface::class), $objectId);

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\Response::class, $response);
    }

    public function testEditActionWithPostRequestValidForm(): void
    {
        $this->setupRequestMockForForm();

        $objectId = 1;
        $this->requestMock->setMethod(Request::METHOD_POST);

        $this->containerMock->expects($this->exactly(3))
            ->method('get')
            ->willReturnCallback(function (...$parameters) {
                if ($parameters[0] === 'router') {
                    return $this->routerMock;
                }
                if ($parameters[0] === 'form.factory') {
                    return $this->createMock(FormFactoryInterface::class);
                }
                if ($parameters[0] === 'twig') {
                    $twigMock = $this->createMock(Environment::class);
                    $twigMock->expects($this->once())
                        ->method('render')
                        ->willReturn('<div>Widget HTML</div>');
                    return $twigMock;
                }
                return null;
            });

        $this->containerMock->expects($this->once())
            ->method('has')
            ->with('twig')
            ->willReturn(true);

        $this->routerMock->expects($this->once())
            ->method('generate')
            ->with('mautic_dashboard_action', ['objectAction' => 'edit', 'objectId' => $objectId])
            ->willReturn('https://some.url');

        $this->modelFactoryMock->expects($this->once())
            ->method('getModel')
            ->with('dashboard')
            ->willReturn($this->dashboardModelMock);

        $widget = new \Mautic\DashboardBundle\Entity\Widget();
        $this->dashboardModelMock->expects($this->once())
            ->method('getEntity')
            ->with($objectId)
            ->willReturn($widget);

        $formMock = $this->createMock(Form::class);
        $formMock->expects($this->once())
            ->method('isSubmitted')
            ->willReturn(true);
        $formMock->expects($this->once())
            ->method('isValid')
            ->willReturn(true);
        $formMock->expects($this->once())
            ->method('get')
            ->with('buttons')
            ->willReturn($this->createMock(Form::class));
        $formMock->expects($this->once())
            ->method('getData')
            ->willReturn($widget);

        $this->dashboardModelMock->expects($this->once())
            ->method('createForm')
            ->willReturn($formMock);

        $this->dashboardModelMock->expects($this->once())
            ->method('saveEntity');

        $this->dashboardModelMock->expects($this->once())
            ->method('getDefaultFilter')
            ->willReturn([]);

        $this->dashboardModelMock->expects($this->once())
            ->method('populateWidgetContent');

        $response = $this->controller->editAction($this->requestMock, $this->createMock(FormFactoryInterface::class), $objectId);

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\JsonResponse::class, $response);

        $responseData = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('widgetId', $responseData);
        $this->assertArrayHasKey('widgetWidth', $responseData);
        $this->assertArrayHasKey('widgetHeight', $responseData);
        $this->assertArrayHasKey('widgetHtml', $responseData);
        $this->assertArrayHasKey('upWidgetCount', $responseData);
        $this->assertArrayHasKey('closeModal', $responseData);
        $this->assertArrayHasKey('mauticContent', $responseData);
    }
}
