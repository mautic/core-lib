<?php

declare(strict_types=1);

namespace Mautic\LeadBundle\Tests\Controller\Api;

use Doctrine\Common\Collections\ArrayCollection;
use Mautic\LeadBundle\Controller\Api\CustomFieldsApiControllerTrait;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Request;

class CustomFieldsApiControllerTraitTest extends \PHPUnit\Framework\TestCase
{
    public function testGetEntityFormOptions(): void
    {
        $modelFake = new class() {
            /**
             * @var array<string,array<string,string>>
             */
            public const FIELD_ARRAY = [
                'field_1' => [
                    'label' => 'Field 1',
                    'type'  => 'text',
                ],
                'field_2' => [
                    'label' => 'Field 2',
                    'type'  => 'text',
                ],
            ];

            /**
             * @var int
             */
            public $getEntitiesCounter = 0;

            /**
             * @return ArrayCollection<string, array{label: string, type: string}>
             */
            public function getEntities(): iterable
            {
                ++$this->getEntitiesCounter;

                return new ArrayCollection(self::FIELD_ARRAY);
            }
        };

        $controller = new class($modelFake) {
            use CustomFieldsApiControllerTrait;

            /**
             * @var object
             */
            private $model;

            /**
             * @var string
             */
            private $entityNameOne = 'lead';

            /**
             * @var Request
             */
            private $request;

            public function __construct(object $modelFake)
            {
                $this->model   = $modelFake;
                $this->request = new Request();
            }

            /**
             * @return mixed[]
             */
            public function getEntityFormOptionsPublic(): array
            {
                return $this->getEntityFormOptions();
            }

            public function getModel(?string $name): object
            {
                return $this->model;
            }
        };

        Assert::assertSame($modelFake::FIELD_ARRAY, (array) $controller->getEntityFormOptionsPublic()['fields']); // Calling once, should be live
        Assert::assertSame($modelFake::FIELD_ARRAY, (array) $controller->getEntityFormOptionsPublic()['fields']); // Calling twice, should be cached
        Assert::assertSame(1, $modelFake->getEntitiesCounter); // Ensure that getEntities is called just once.
    }
}
