<?php

namespace App\Katzen\Command;

use App\Katzen\Entity\Import\ImportMapping;
use App\Katzen\Repository\Import\ImportMappingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'import:create-test-mappings',
    description: 'Create test import mappings for orders'
)]
class CreateOrderMappingCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private ImportMappingRepository $mappingRepo,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $orderMapping = new ImportMapping();
        $orderMapping->setName('Test Orders Import');
        $orderMapping->setEntityType('order');
        $orderMapping->setDescription('Test mapping for basic order imports');
        $orderMapping->setIsSystemTemplate(false);
        $orderMapping->setIsActive(true);
        
        $orderMapping->setFieldMappings([
            'Order Number' => 'order_number',
            'Customer Name' => 'customer',
            'Item SKU' => 'item_sku',
            'Quantity' => 'quantity',
            'Unit Price' => 'unit_price',
            'Order Date' => 'order_date',
        ]);

        $orderMapping->setValidationRules([
            'Quantity' => [
                'min' => ['value' => 1, 'severity' => 'error'],
            ],
            'Unit Price' => [
                'min' => ['value' => 0, 'severity' => 'error'],
            ],
        ]);

        $this->em->persist($orderMapping);
        $output->writeln('Created Orders mapping');

        $this->em->flush();
        $output->writeln("\nAll test mappings created successfully!");

        return Command::SUCCESS;
    }
}
