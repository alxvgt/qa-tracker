<?php

namespace Alxvng\QATracker\Command;

use Alxvng\QATracker\Chart\Chart;
use Alxvng\QATracker\Chart\ChartGenerator;
use Alxvng\QATracker\Configuration\Configuration;
use Alxvng\QATracker\DataProvider\Model\AbstractDataSerie;
use Alxvng\QATracker\DataProvider\Model\DataPercentSerie;
use Alxvng\QATracker\DataProvider\Model\DataStandardSerie;
use Alxvng\QATracker\Root\Root;
use Alxvng\QATracker\Twig\TwigFactory;
use DateTime;
use Goat1000\SVGGraph\SVGGraph;
use JsonException;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class TrackCommand extends Command
{
    public const VERSION = 'v0.6.0';

    public const EXIT_SUCCESS = 0;
    public const EXIT_FAILURE = 1;

    public const BASE_DIR = '.qatracker';
    public const GENERATED_DIR = 'generated';
    public const OUTPUT_DIR = 'report';
    public const OUTPUT_FILENAME = 'index.html';
    public const DEFAULT_TEMPLATE = 'index.html.twig';
    public const CONFIG_FILENAME = 'config.yaml';
    protected const OUTPUT_DONE = ' <fg=green>done</>.';

    protected static string $baseDir = self::BASE_DIR;
    protected static DateTime $trackDate;

    protected static $defaultName = 'track';

    public static function getBaseDir(): string
    {
        return Root::external().'/'.static::$baseDir;
    }

    public static function getGeneratedDir(): string
    {
        return static::getBaseDir().'/'.static::GENERATED_DIR;
    }

    public static function getConfigPath(): string
    {
        return static::getBaseDir().'/'.static::CONFIG_FILENAME;
    }

    /**
     * @param $trackDate
     */
    public static function initializeTrackDate($trackDate): void
    {
        static::$trackDate = DateTime::createFromFormat(AbstractDataSerie::DATE_FORMAT, $trackDate);

        if (!static::$trackDate) {
            throw new \RuntimeException(sprintf('Unable to create date from option with value %s', $trackDate));
        }
    }

    /**
     * @return DateTime|false
     */
    public static function getTrackDate()
    {
        return self::$trackDate;
    }

    protected function configure()
    {
        $outputDir = static::getGeneratedDir().'/'.static::OUTPUT_DIR;

        $this
            ->setDescription('Track your QA indicators')
            ->setHelp('This command allows you to fetch some indicators and build simple QA charts...')
            ->addOption(
                'date',
                null,
                InputOption::VALUE_REQUIRED,
                sprintf('Use this date instead today to collect data (use format "%s")', AbstractDataSerie::DATE_FORMAT),
                (new DateTime())->format(AbstractDataSerie::DATE_FORMAT)
            )
            ->addOption('no-report', null, InputOption::VALUE_NONE, 'Do not execute the report rendering step')
            ->addOption('no-track', null, InputOption::VALUE_NONE, 'Do not execute the tracking step')
            ->addOption(
                'report-html-path',
                null,
                InputOption::VALUE_REQUIRED,
                'Define a custom path for the html report',
                $outputDir.'/'.static::OUTPUT_FILENAME
            )
            ->addOption(
                'base-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Define a custom base directory for qatracker',
                static::getBaseDir()
            )
        ;
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        static::$baseDir = (string) $input->getOption('base-dir');

        if (file_exists(static::getConfigPath())) {
            return;
        }

        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            sprintf(
                "\nFile %s does not exists.\nDo you want to create it from the sample file ? (Y/n)",
                static::getConfigPath()
            ),
            true
        );

        if (!$helper->ask($input, $output, $question)) {
            $io->warning(sprintf('The config file has not been created'));
            $this->setCode(function () {
                return static::EXIT_SUCCESS;
            });
        }

        $fs = new Filesystem();
        $fs->copy(Configuration::exampleConfigPath(), static::getConfigPath());

        $io->success(sprintf(
            "The config file has been created at \"%s\".\nYou can now edit it to put your own configuration.",
            static::getConfigPath()
        ));

        $this->setCode(function () {
            return static::EXIT_SUCCESS;
        });
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @throws JsonException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $twig = TwigFactory::getTwig();
        try {
            $io->title('Track your QA indicators');

            /** @var ConsoleSectionOutput $section */
            $section = $output->section();
            $message = 'Initializing...';
            $section->writeln($message);
            $outputFilePath = $input->getOption('report-html-path');
            $this->initializeOutputDir($outputFilePath);
            $trackDate = $input->getOption('date');
            static::initializeTrackDate($trackDate);
            $config = Configuration::load(static::getConfigPath());
            $section->overwrite($message.static::OUTPUT_DONE);

            /** @var ConsoleSectionOutput $section */
            $section = $output->section();
            $message = 'Loading data series...';
            $section->writeln($message);
            $dataSeriesConfig = $config['qatracker']['dataSeries'];
            $dataSeriesStack = $this->loadDataSeries($dataSeriesConfig);
            $section->overwrite($message.static::OUTPUT_DONE);
            $io->newLine();

            $withTrack = !$input->getOption('no-track');
            $withReport = !$input->getOption('no-report');
            $graphs = [];

            if ($withTrack) {
                /** @var AbstractDataSerie $dataSerie */
                foreach ($dataSeriesStack as $dataSerie) {
                    /** @var ConsoleSectionOutput $section */
                    $section = $output->section();
                    $message = sprintf('Collecting new indicator for "%s"...', $dataSerie->getId());
                    $section->writeln($message);
                    $dataSerie->collect(static::getTrackDate());
                    $section->overwrite($message.static::OUTPUT_DONE);
                }
                $io->newLine();
            }

            if ($withReport) {
                /** @var ConsoleSectionOutput $section */
                $section = $output->section();
                $message = 'Generating charts : ';
                $section->writeln($message);

                $charts = $config['qatracker']['charts'];
                foreach ($charts as $chart) {
                    $chart = new Chart($chart, $dataSeriesStack);

                    $graphs[] = ChartGenerator::generate(
                        $chart->getFirstProvider()->getData(),
                        $chart->getType(),
                        $chart->getGraphSettings()
                    );
                    $message .= '.';
                    $section->overwrite($message);
                }
                $io->newLine();
            }

            if (!empty($graphs)) {
                /** @var ConsoleSectionOutput $section */
                $section = $output->section();
                $message = sprintf('Rendering report...');
                $section->writeln($message);

                $html = $twig->render(static::DEFAULT_TEMPLATE, [
                    'graphs' => $graphs,
                    'js' => SVGGraph::fetchJavascript(),
                    'version' => static::VERSION,
                    'generatedAt' => new DateTime(),
                ]);
                file_put_contents($outputFilePath, $html);
                $section->overwrite($message.static::OUTPUT_DONE);
                $io->newLine();
            }

            $io->success(
                [
                    sprintf(
                        "Well done ! You have track new QA indicators !\n".
                        'Report generated at : %s',
                        $outputFilePath
                    ),
                ]
            );

            return static::EXIT_SUCCESS;
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());

            return static::EXIT_FAILURE;
        }
    }

    /**
     * @param $outputFilePath
     */
    protected function initializeOutputDir(string $outputFilePath): void
    {
        $outputFileDir = dirname($outputFilePath);

        if (!is_dir($outputFileDir) && !mkdir($outputFileDir, 0777, true)) {
            throw new RuntimeException(sprintf('Directory " % s" was not created', $outputFileDir));
        }
    }

    /**
     * @param $providersConfig
     *
     * @throws JsonException
     */
    protected function loadDataSeries($providersConfig): array
    {
        $dataSeriesStack = [];

        /*
         * Load standard providers on a first time
         */
        foreach ($providersConfig as $key => $provider) {
            if (!AbstractDataSerie::isStandard($provider)) {
                continue;
            }

            $provider = new DataStandardSerie($provider, TrackCommand::getGeneratedDir());
            $dataSeriesStack[$provider->getId()] = $provider;
            unset($providersConfig[$key]);
        }

        /*
         * Load other providers on a second time
         */
        foreach ($providersConfig as $key => $provider) {
            if (!AbstractDataSerie::isPercent($provider)) {
                continue;
            }

            $provider = new DataPercentSerie($provider, TrackCommand::getGeneratedDir(), $dataSeriesStack);
            $dataSeriesStack[$provider->getId()] = $provider;
            unset($providersConfig[$key]);
        }

        return $dataSeriesStack;
    }
}
