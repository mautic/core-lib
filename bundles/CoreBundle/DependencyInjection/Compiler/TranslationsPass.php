<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.

 Modified for
 * @package     Mautic
 * @copyright   2014 Mautic, NP. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.com
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\CoreBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\Finder\Finder;

class TranslationsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $translator = $container->findDefinition('translator.default');
        $factory    = $container->get('mautic.factory');
        if ($translator !== null) {
            $supportedLanguages = $factory->getParameter('supported_languages');

            foreach ($supportedLanguages as $locale => $name) {
                //force the mautic translation loader
                $translator->addMethodCall('addResource', array(
                    'mautic', null, $locale, 'messages'
                ));
            }
        }
    }
}
