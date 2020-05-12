<?php

declare(strict_types=1);

namespace GrumPHP\Task;

use GrumPHP\Collection\FilesCollection;
use GrumPHP\Runner\TaskResult;
use GrumPHP\Runner\TaskResultInterface;
use GrumPHP\Task\Context\ContextInterface;
use GrumPHP\Task\Context\GitPreCommitContext;
use GrumPHP\Task\Context\RunContext;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Phan task.
 */
class Phan extends AbstractExternalTask
{
    public static function getConfigurableOptions(): OptionsResolver
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults(
            [
                'output_mode' => 'text',
                'output' => null,
                'config_file' => '.phan/config.php',
                'triggered_by' => ['php'],
                'diff_only' => false,
            ]
        );

        $resolver->addAllowedTypes('output_mode', ['string']);
        $resolver->addAllowedTypes('output', ['null', 'string']);
        $resolver->addAllowedTypes('config_file', ['string']);
        $resolver->addAllowedTypes('triggered_by', ['array']);
        $resolver->addAllowedTypes('diff_only', ['boolean']);

        return $resolver;
    }

    /**
     * {@inheritdoc}
     */
    public function canRunInContext(ContextInterface $context): bool
    {
        return $context instanceof GitPreCommitContext || $context instanceof RunContext;
    }

    /**
     * {@inheritdoc}
     */
    public function run(ContextInterface $context): TaskResultInterface
    {
        $config = $this->getConfig()->getOptions();
        $files = $context->getFiles()->extensions($config['triggered_by']);
        if (0 === \count($files)) {
            return TaskResult::createSkipped($this, $context);
        }

        $config = $this->getConfig()->getOptions();

        $arguments = $this->processBuilder->createArgumentsForCommand('phan');

        $arguments->addOptionalArgumentWithSeparatedValue('--config-file', $config['config_file']);
        $arguments->addOptionalArgumentWithSeparatedValue('--output-mode', $config['output_mode']);
        $arguments->addOptionalArgumentWithSeparatedValue('--output', $config['output']);

        if ($config['diff_only']) {
            $paths = [];

            foreach ($files as $file) {
                $paths[] = $file->getPathname();
            }

            $arguments->addOptionalArgumentWithSeparatedValue('--include-analysis-file-list', implode(',', $paths));
        }

        $process = $this->processBuilder->buildProcess($arguments);
        $process->run();

        if (!$process->isSuccessful()) {
            return TaskResult::createFailed($this, $context, $this->formatter->format($process));
        }

        return TaskResult::createPassed($this, $context);
    }
}
