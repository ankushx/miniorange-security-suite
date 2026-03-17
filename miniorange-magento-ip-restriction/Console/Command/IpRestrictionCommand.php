<?php

namespace MiniOrange\IpRestriction\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use MiniOrange\IpRestriction\Helper\Data;
use MiniOrange\IpRestriction\Helper\IpRestrictionConstants;
use Magento\Framework\Console\Cli;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Cache\TypeListInterface;

/**
 * CLI command to enable/disable entire IP restriction feature
 * Controls both IP blacklist and country restrictions
 */
class IpRestrictionCommand extends Command
{
    /**
     * @var Data
     */
    protected $dataHelper;

    /**
     * @var TypeListInterface
     */
    protected $cacheTypeList;

    public function __construct(
        Data $dataHelper,
        TypeListInterface $cacheTypeList
    ) {
        $this->dataHelper = $dataHelper;
        $this->cacheTypeList = $cacheTypeList;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('admin:ip-restriction');
        $this->setDescription('Enable or disable entire IP Restriction feature');
        
        $this->addOption(
            'disable',
            null,
            InputOption::VALUE_NONE,
            'Disable IP Restriction feature'
        );
        
        $this->addOption(
            'enable',
            null,
            InputOption::VALUE_NONE,
            'Enable IP Restriction feature'
        );
        
        $this->addOption(
            'status',
            null,
            InputOption::VALUE_NONE,
            'Check current status of IP Restriction feature'
        );

        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $disableMode = $input->getOption('disable');
            $enableMode = $input->getOption('enable');
            $statusMode = $input->getOption('status');

            // Validate that only one option is provided
            $optionsCount = (int)$disableMode + (int)$enableMode + (int)$statusMode;
            if ($optionsCount === 0) {
                $output->writeln('<error>Error: One of --disable, --enable, or --status is required</error>');
                $output->writeln('<comment>Usage: bin/magento admin:ip-restriction --disable|--enable|--status</comment>');
                return Cli::RETURN_FAILURE;
            }

            if ($optionsCount > 1) {
                $output->writeln('<error>Error: Only one option can be used at a time (--disable, --enable, or --status)</error>');
                return Cli::RETURN_FAILURE;
            }

            if ($statusMode) {
                return $this->showStatus($output);
            }

            if ($disableMode) {
                return $this->disableIpRestriction($output);
            }

            if ($enableMode) {
                return $this->enableIpRestriction($output);
            }

            // This should never be reached due to validation, but ensures return
            return Cli::RETURN_FAILURE;
        } catch (\Exception $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return Cli::RETURN_FAILURE;
        }
    }

    /**
     * Disable IP restriction feature
     */
    private function disableIpRestriction(OutputInterface $output): int
    {
        // Set master disable flag
        $this->dataHelper->setStoreConfig(
            IpRestrictionConstants::IP_RESTRICTION_DISABLED,
            '1',
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            0
        );

        $this->cacheTypeList->invalidate('config');

        $output->writeln('<info>IP Restriction feature has been disabled.</info>');

        return Cli::RETURN_SUCCESS;
    }

    /**
     * Enable IP restriction feature
     */
    private function enableIpRestriction(OutputInterface $output): int
    {
        // Remove master disable flag
        $this->dataHelper->setStoreConfig(
            IpRestrictionConstants::IP_RESTRICTION_DISABLED,
            '0',
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            0
        );

        $this->cacheTypeList->invalidate('config');

        $output->writeln('<info>IP Restriction feature has been enabled.</info>');

        return Cli::RETURN_SUCCESS;
    }

    /**
     * Show status of IP restriction feature
     */
    private function showStatus(OutputInterface $output): int
    {
        $isDisabled = $this->dataHelper->getStoreConfig(
            IpRestrictionConstants::IP_RESTRICTION_DISABLED,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            0
        );

        $output->writeln('IP Restriction: ' . ($isDisabled == '1' ? '<comment>disabled</comment>' : '<info>enabled</info>'));

        return Cli::RETURN_SUCCESS;
    }
}

