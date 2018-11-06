<?php

declare(strict_types=1);

namespace Tests\Setono\SyliusMailChimpPlugin\Behat\Context\Cli;

use Behat\Behat\Context\Context;
use Setono\SyliusMailChimpPlugin\Entity\MailChimpConfigInterface;
use Setono\SyliusMailChimpPlugin\Repository\MailChimpConfigRepositoryInterface;
use Sylius\Behat\NotificationType;
use Sylius\Behat\Service\NotificationCheckerInterface;
use Sylius\Behat\Service\SharedStorageInterface;
use Sylius\Bundle\CoreBundle\Command\SetupCommand;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Sylius\Component\Customer\Model\CustomerInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;
use Tests\Setono\SyliusMailChimpPlugin\Behat\Page\Admin\ExportCustomers\IndexPageInterface;
use Tests\Setono\SyliusMailChimpPlugin\Behat\Service\RandomStringGeneratorInterface;
use Webmozart\Assert\Assert;

final class MailChimpExportCliContext implements Context
{
    /** @var KernelInterface */
    private $kernel;

    /** @var Application */
    private $application;

    /** @var CommandTester */
    private $tester;

    /** @var SetupCommand */
    private $command;

    /** @var IndexPageInterface */
    private $indexPage;

    /** @var MailChimpConfigRepositoryInterface */
    private $mailChimpConfigRepository;

    /** @var RandomStringGeneratorInterface */
    private $randomStringGenerator;

    /** @var FactoryInterface */
    private $configFactory;

    /** @var SharedStorageInterface */
    private $sharedStorage;

    /** @var FactoryInterface */
    private $customerFactory;

    /** @var CustomerRepositoryInterface */
    private $customerRepository;

    /** @var NotificationCheckerInterface */
    private $notificationChecker;

    public function __construct(
        KernelInterface $kernel,
        IndexPageInterface $indexPage,
        MailChimpConfigRepositoryInterface $mailChimpConfigRepository,
        RandomStringGeneratorInterface $randomStringGenerator,
        FactoryInterface $configFactory,
        SharedStorageInterface $sharedStorage,
        FactoryInterface $customerFactory,
        CustomerRepositoryInterface $customerRepository,
        NotificationCheckerInterface $notificationChecker
    ) {
        $this->kernel = $kernel;
        $this->indexPage = $indexPage;
        $this->mailChimpConfigRepository = $mailChimpConfigRepository;
        $this->randomStringGenerator = $randomStringGenerator;
        $this->configFactory = $configFactory;
        $this->sharedStorage = $sharedStorage;
        $this->customerFactory = $customerFactory;
        $this->customerRepository = $customerRepository;
        $this->notificationChecker = $notificationChecker;
    }

    /**
     * @When I execute the MailChimp export command
     */
    public function iExecuteTheMailchimpExportCommand(): void
    {
        $this->application = new Application($this->kernel);
        $this->command = new Command();

        $this->command = $this->application->find('setono:mailchimp:export');

        $this->application->add($this->command);
        $this->tester = new CommandTester($this->command);

        $this->tester->execute([]);
    }

    /**
     * @Then I should see an error saying that I need to set up the MaiLChimp config first
     */
    public function iShouldSeeAnErrorSayingThatINeedToSetUpTheMailchimpConfigFirst(): void
    {
        Assert::contains($this->tester->getDisplay(), 'Command executed.');
    }

    /**
     * @Given I have a MailChimp config set up
     */
    public function iHaveAMailchimpConfigSetUp(): void
    {
        $config = $this->createConfig();
        $this->saveConfig($config);
    }

    /**
     * @Given I allow all emails to be exported
     */
    public function iAllowAllEmailsToBeExported(): void
    {
        $config = $this->sharedStorage->get('config');

        Assert::true($config->getExportAll());
    }

    /**
     * @Given I have :count customers in my database
     */
    public function iHaveCustomersInMyDatabase(int $count): void
    {
        for ($i = 0; $i < $count; ++$i) {
            /** @var CustomerInterface $customer */
            $customer = $this->customerFactory->createNew();

            $customer->setEmail(
                $this->randomStringGenerator->generate(5) . '@' .
                $this->randomStringGenerator->generate(5) . '.com'
            );
            $customer->setSubscribedToNewsletter(true);

            $this->customerRepository->add($customer);
        }
    }

    /**
     * @Then a new export with :state state should be created
     */
    public function aNewExportWithStateShouldBeCreated(string $state): void
    {
        $this->indexPage->open();

        Assert::eq(current($this->indexPage->getColumnFields('state')), $state);
    }

    /**
     * @When I wait :seconds seconds
     */
    public function iWaitSeconds(int $seconds): void
    {
        sleep($seconds);
    }

    /**
     * @Then I should be notified that the export has succeeded
     */
    public function iShouldBeNotifiedThatTheExportHasSucceeded(): void
    {
        $this->notificationChecker->checkNotification('Export succeded', NotificationType::success());
    }

    /**
     * @When I refresh the page again
     */
    public function iRefreshThePageAgain(): void
    {
        $this->indexPage->open();
    }

    /**
     * @Then the export should have :state state
     */
    public function theExportShouldHaveState(string $state): void
    {
        $this->indexPage->open();

        Assert::eq($this->indexPage->getState($state), $state);
    }

    /**
     * @Then :count emails should be exported for it
     * @Then :count emails have been exported
     */
    public function emailsShouldBeExportedForIt(int $count): void
    {
        Assert::eq(current($this->indexPage->getColumnFields('emails_count')), $count);
    }

    private function createConfig(
        ?string $code = null,
        ?string $apiKey = null
    ): MailChimpConfigInterface {
        /** @var MailChimpConfigInterface $config */
        $config = $this->configFactory->createNew();

        $config->setCode($code ?? $this->randomStringGenerator->generate(10));
        $config->setApiKey($apiKey ?? $this->randomStringGenerator->generate(10));

        $config->setExportAll(true);

        return $config;
    }

    private function saveConfig(MailChimpConfigInterface $config): void
    {
        $this->mailChimpConfigRepository->add($config);

        $this->sharedStorage->set('config', $config);
    }
}