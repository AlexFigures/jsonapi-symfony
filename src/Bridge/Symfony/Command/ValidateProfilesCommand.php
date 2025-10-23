<?php

declare(strict_types=1);

namespace AlexFigures\Symfony\Bridge\Symfony\Command;

use AlexFigures\Symfony\Profile\AttributeReader;
use AlexFigures\Symfony\Profile\ProfileInterface;
use AlexFigures\Symfony\Profile\ProfileRegistry;
use AlexFigures\Symfony\Profile\Validation\ProfileValidator;
use AlexFigures\Symfony\Resource\Registry\ResourceRegistryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Console command to validate profile requirements.
 *
 * This command performs the same validation as the ValidateProfilesPass compiler pass,
 * but can be run manually for debugging or CI/CD pipelines.
 *
 * Usage:
 *   php bin/console jsonapi:validate-profiles
 */
#[AsCommand(
    name: 'jsonapi:validate-profiles',
    description: 'Validate that all enabled profiles have their requirements satisfied'
)]
final class ValidateProfilesCommand extends Command
{
    public function __construct(
        private readonly ProfileRegistry $profileRegistry,
        private readonly ResourceRegistryInterface $resourceRegistry,
        private readonly EntityManagerInterface $entityManager,
        private readonly ParameterBagInterface $params,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('JSON:API Profile Validation');

        // Collect all profiles
        $profilesByUri = $this->collectProfiles();
        $io->info(sprintf('Found %d registered profile(s)', count($profilesByUri)));

        // Collect all resource types
        $resourceTypes = $this->collectResourceTypes();
        $io->info(sprintf('Found %d resource type(s)', count($resourceTypes)));

        // Collect enabled profiles
        $enabledProfiles = $this->collectEnabledProfiles($resourceTypes);
        $totalEnabled = array_sum(array_map('count', $enabledProfiles));
        $io->info(sprintf('Found %d enabled profile assignment(s)', $totalEnabled));

        if ($totalEnabled === 0) {
            $io->success('No profiles are enabled. Nothing to validate.');
            return Command::SUCCESS;
        }

        // Create validator and validate
        $validator = new ProfileValidator(
            $this->entityManager,
            new AttributeReader()
        );

        $result = $validator->validate($profilesByUri, $resourceTypes, $enabledProfiles);

        // Display results
        if ($result->hasErrors()) {
            $io->error(sprintf('Validation failed with %d error(s):', $result->getErrorCount()));
            foreach ($result->formatErrors() as $error) {
                $io->writeln('  ' . $error);
            }
        }

        if ($result->hasWarnings()) {
            $io->warning(sprintf('Found %d warning(s):', $result->getWarningCount()));
            foreach ($result->formatWarnings() as $warning) {
                $io->writeln('  ' . $warning);
            }
        }

        if ($result->isValid() && !$result->hasWarnings()) {
            $io->success('All profiles are valid!');
            return Command::SUCCESS;
        }

        if ($result->isValid()) {
            $io->success('Validation passed (with warnings)');
            return Command::SUCCESS;
        }

        return Command::FAILURE;
    }

    /**
     * Collect all registered profiles.
     *
     * @return array<string, ProfileInterface>
     */
    private function collectProfiles(): array
    {
        $profiles = [];
        foreach ($this->profileRegistry->all() as $profile) {
            $profiles[$profile->uri()] = $profile;
        }
        return $profiles;
    }

    /**
     * Collect all resource types and their entity classes.
     *
     * @return array<string, class-string>
     */
    private function collectResourceTypes(): array
    {
        $resourceTypes = [];
        foreach ($this->resourceRegistry->all() as $metadata) {
            $resourceTypes[$metadata->type] = $metadata->class;
        }
        return $resourceTypes;
    }

    /**
     * Collect enabled profiles per resource type.
     *
     * @param  array<string, class-string> $resourceTypes
     * @return array<string, list<string>>
     */
    private function collectEnabledProfiles(array $resourceTypes): array
    {
        $enabledProfiles = [];

        // Get globally enabled profiles
        $globalProfiles = [];
        if ($this->params->has('jsonapi.profiles.enabled_by_default')) {
            /** @var list<string> $globalProfiles */
            $globalProfiles = $this->params->get('jsonapi.profiles.enabled_by_default');
        }

        // Get per-type enabled profiles
        $perTypeProfiles = [];
        if ($this->params->has('jsonapi.profiles.per_type')) {
            /** @var array<string, list<string>> $perTypeProfiles */
            $perTypeProfiles = $this->params->get('jsonapi.profiles.per_type');
        }

        // Merge global and per-type profiles
        foreach ($resourceTypes as $resourceType => $entityClass) {
            $profiles = $globalProfiles;

            if (isset($perTypeProfiles[$resourceType])) {
                $profiles = array_values(array_unique(array_merge($profiles, $perTypeProfiles[$resourceType])));
            }

            if (!empty($profiles)) {
                $enabledProfiles[$resourceType] = $profiles;
            }
        }

        return $enabledProfiles;
    }
}
