<?php

namespace Mautic\ComposerBundle;

use Composer\Installer\PackageEvent;
use Composer\Script\Event;
use Composer\Semver\Constraint\Constraint;
use Composer\Util\ProcessExecutor;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Provides static functions for composer script events.
 *
 * @see https://getcomposer.org/doc/articles/scripts.md
 */
class ComposerBundle {

  /**
   * Add vendor classes to Composer's static classmap.
   *
   * @param \Composer\Script\Event $event
   */
  public static function preAutoloadDump(Event $event) {
    // Get the configured vendor directory.
    $vendor_dir = $event->getComposer()->getConfig()->get('vendor-dir');

    // We need the root package so we can add our classmaps to its loader.
    $package = $event->getComposer()->getPackage();
    // We need the local repository so that we can query and see if it's likely
    // that our files are present there.
    $repository = $event->getComposer()->getRepositoryManager()->getLocalRepository();
    // This is, essentially, a null constraint. We only care whether the package
    // is present in the vendor directory yet, but findPackage() requires it.
    $constraint = new Constraint('>', '');
    // It's possible that there is no classmap specified in a custom project
    // composer.json file. We need one so we can optimize lookup for some of our
    // dependencies.
    $autoload = $package->getAutoload();
    if (!isset($autoload['classmap'])) {
      $autoload['classmap'] = [];
    }
    // Check for our packages, and then optimize them if they're present.
    if ($repository->findPackage('symfony/http-foundation', $constraint)) {
      $autoload['classmap'] = array_merge($autoload['classmap'], [
        $vendor_dir . '/symfony/http-foundation/Request.php',
        $vendor_dir . '/symfony/http-foundation/ParameterBag.php',
        $vendor_dir . '/symfony/http-foundation/FileBag.php',
        $vendor_dir . '/symfony/http-foundation/ServerBag.php',
        $vendor_dir . '/symfony/http-foundation/HeaderBag.php',
      ]);
    }
    if ($repository->findPackage('symfony/http-kernel', $constraint)) {
      $autoload['classmap'] = array_merge($autoload['classmap'], [
        $vendor_dir . '/symfony/http-kernel/HttpKernel.php',
        $vendor_dir . '/symfony/http-kernel/HttpKernelInterface.php',
        $vendor_dir . '/symfony/http-kernel/TerminableInterface.php',
      ]);
    }
    $package->setAutoload($autoload);
  }

  /**
   * Removes Composer's timeout so that scripts can run indefinitely.
   */
  public static function removeTimeout() {
    ProcessExecutor::setTimeout(0);
  }

}
