<?php
// This file is copied from config/symfony/php/.php-cs-fixer.dist.php in https://github.com/itk-dev/devops_itkdev-docker.
// Feel free to edit the file, but consider making a pull request if you find a general issue with the file.

// https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/blob/master/doc/config.rst

$finder = new PhpCsFixer\Finder();
// Check all files …
$finder->in(__DIR__);
// … that are not ignored by VCS
$finder->ignoreVCSIgnored(true);
// … and that are not from Symfony or Flex. We do not edit these files.
$finder->notPath([
  'config/bundles.php',
  'config/preload.php',
  'config/reference.php',
  'importmap.php',
  'public/index.php',
]);

$config = new PhpCsFixer\Config();
$config->setFinder($finder);

// declare_strict_types is a risky rule. @Symfony has no risky rules.
$config->setRiskyAllowed(true);
$config->setRules([
  '@Symfony' => true,
  'declare_strict_types' => true,
]);

return $config;
