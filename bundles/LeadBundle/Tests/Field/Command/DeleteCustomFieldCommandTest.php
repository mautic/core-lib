<?php

/*
 * @package     Mautic
 * @copyright   2020 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\LeadBundle\Tests\Field\Command;

use Mautic\LeadBundle\Field\BackgroundService;
use Mautic\LeadBundle\Field\Command\DeleteCustomFieldCommand;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Translation\TranslatorInterface;

class DeleteCustomFieldCommandTest extends TestCase
{
    /**
     * @var MockObject|BackgroundService
     */
    private $backgroundServiceMock;

    /**
     * @var MockObject|TranslatorInterface
     */
    private $translatorInterfaceMock;

    /**
     * @var DeleteCustomFieldCommand
     */
    private $deleteCustomFieldCommand;

    protected function setUp()
    {
        $this->backgroundServiceMock    = $this->createMock(BackgroundService::class);
        $this->translatorInterfaceMock  = $this->createMock(TranslatorInterface::class);
        $this->deleteCustomFieldCommand = new DeleteCustomFieldCommand(
            $this->backgroundServiceMock,
            $this->translatorInterfaceMock
        );
    }

    public function testExecute()
    {
        $this->backgroundServiceMock
            ->expects($this->once())
            ->method('deleteColumn')
            ->with(42, 0);
        $this->translatorInterfaceMock
            ->expects($this->once())
            ->method('trans')
            ->with('mautic.lead.field.column_was_deleted')
            ->willReturn('Column was deleted');
        $commandTester = new CommandTester($this->deleteCustomFieldCommand);
        $commandTester->execute([
            // pass arguments to the command
            '--id'   => '42',
            '--user' => '0',
        ]);

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Column was deleted', $output);
    }
}
