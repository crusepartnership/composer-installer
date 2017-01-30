<?php
namespace Lucidity\Composer;

use Closure;
use Composer\Composer;
use Composer\Installer\LibraryInstaller;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;

class ModuleInstaller extends LibraryInstaller
{
    /**
     * @var null|string
     */
    protected $localModuleDirectory = null;

    /**
     * @var bool
     */
    protected $localInstallsAllowed = true;

    /**
     * @var string
     */
    protected $supportedPackageType;

    /**
     * @var Closure
     */
    private $installPathResolver;

    public function __construct(IOInterface $io, Composer $composer, $supportedPackageType, Closure $installPathResolver)
    {
        $this->supportedPackageType = $supportedPackageType;
        $this->installPathResolver = $installPathResolver;
        parent::__construct($io, $composer, 'library', new SymlinkFilesystem());
        $this->setLocalModuleDirectory();
    }

    public function setLocalInstallsAllowed($allowed)
    {
        $this->localInstallsAllowed = $allowed;
        return $this;
    }

    public function supports($packageType)
    {
        return $packageType === $this->supportedPackageType;
    }

    public function getInstallPath(PackageInterface $package)
    {
        return call_user_func($this->installPathResolver, $this->getPackageName($package));
    }

    private function getPackageName(PackageInterface $package)
    {
        return substr($package->getPrettyName(), strpos($package->getPrettyName(), '/') + 1);
    }

    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        if ($this->localModuleExists($package)) {
            $this->filesystem->ensureSymlinkExists($this->localPackagePath($package), $this->getInstallPath($package));
            $this->io->writeError(' - Linking <info>' . $package->getName() . '</info> from <info>' . $this->localPackagePath($package) . '</info>');
            if (!$repo->hasPackage($package)) {
                $repo->addPackage(clone $package);
            }
        } else {
            parent::install($repo, $package);
        }
    }

    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
    {
        if ($this->localModuleExists($target)) {
            $this->install($repo, $initial);
        } else {
            parent::update($repo, $initial, $target);
        }
    }

    public function isInstalled(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        if ($this->localModuleExists($package)) {
            return $this->filesystem->isSymlinkedDirectory($this->localPackagePath($package));
        }
        return parent::isInstalled($repo, $package); // TODO: Change the autogenerated stub
    }

    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        if ($this->localModuleExists($package) && $this->filesystem->isSymlinkedDirectory($this->localPackagePath($package))) {
            $this->filesystem->removeDirectory($this->localPackagePath($package));
        } else {
            parent::uninstall($repo, $package);
        }
    }

    private function localModuleExists(PackageInterface $package)
    {
        return $this->localInstallsAllowed && is_dir($this->localPackagePath($package));
    }

    public function setLocalModuleDirectory($directory = null)
    {
        if ($directory !== null) {
            $this->localModuleDirectory = $directory;
        } else {
            $this->localModuleDirectory = realpath($this->composer->getConfig()->get('vendor-dir') . '/../../');
        }
        return $this;
    }

    private function localPackagePath(PackageInterface $package)
    {
        return $this->localModuleDirectory . '/' . $this->getPackageName($package);
    }
}