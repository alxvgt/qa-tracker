#!/usr/bin/env php
<?php

require __DIR__.'/vendor/autoload.php';

use App\Command\TrackCommand;
use Symfony\Component\Console\Application;

$application = new Application();
$command = new TrackCommand();
$application->add(new TrackCommand());

$application->setDefaultCommand($command->getName(), true);
$application->run();