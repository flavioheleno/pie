<?php

declare(strict_types=1);

namespace Php\Pie\Command;

use Composer\Package\Version\VersionParser;
use InvalidArgumentException;
use Php\Pie\DependencyResolver\DependencyResolver;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Assert\Assert;

use function array_key_exists;
use function is_array;
use function is_string;
use function reset;
use function sprintf;

use const PHP_VERSION;

#[AsCommand(
    name: 'download',
    description: 'Same behaviour as build, but puts the files in a local directory for manual building and installation.',
)]
final class DownloadCommand extends Command
{
    private const ARG_REQUESTED_PACKAGE_AND_VERSION = 'requested-package-and-version';

    public function __construct(private readonly DependencyResolver $dependencyResolver)
    {
        parent::__construct();
    }

    public function configure(): void
    {
        parent::configure();

        $this->addArgument(
            self::ARG_REQUESTED_PACKAGE_AND_VERSION,
            InputArgument::REQUIRED,
            'The extension name and version constraint to use, in the format {ext-name}{?:version-constraint}{?@dev-branch-name}, for example `ext-debug:^1.0`',
        );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $requestedNameAndVersionPair = $this->requestedNameAndVersionPair($input);

        $package = ($this->dependencyResolver)(
            $requestedNameAndVersionPair['name'],
            $requestedNameAndVersionPair['version'],
        );

        $output->writeln(sprintf('<info>You are running PHP %s</info>', PHP_VERSION));
        $output->writeln(sprintf('<info>Found package:</info> %s (version: %s)', $package->name, $package->version));
        $output->writeln(sprintf('Dist download URL: %s', $package->downloadUrl ?? '(none)'));

        return Command::SUCCESS;
    }

    /** @return array{name: non-empty-string, version: non-empty-string|null} */
    private function requestedNameAndVersionPair(InputInterface $input): array
    {
        $requestedPackageString = $input->getArgument(self::ARG_REQUESTED_PACKAGE_AND_VERSION);

        if (! is_string($requestedPackageString) || $requestedPackageString === '') {
            throw new InvalidArgumentException('No package was requested for installation');
        }

        $nameAndVersionPairs         = (new VersionParser())
            ->parseNameVersionPairs([$requestedPackageString]);
        $requestedNameAndVersionPair = reset($nameAndVersionPairs);

        if (! is_array($requestedNameAndVersionPair)) {
            throw new InvalidArgumentException('Failed to parse the name/version pair');
        }

        if (! array_key_exists('version', $requestedNameAndVersionPair)) {
            $requestedNameAndVersionPair['version'] = null;
        }

        Assert::stringNotEmpty($requestedNameAndVersionPair['name']);
        Assert::nullOrStringNotEmpty($requestedNameAndVersionPair['version']);

        return $requestedNameAndVersionPair;
    }
}
