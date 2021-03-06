<?php

namespace REBELinBLUE\Deployer\Console\Commands;

use Illuminate\Console\Command;
use REBELinBLUE\Deployer\Services\Filesystem\Filesystem;

/**
 * Clears out any temp SSH keys and wrapper scripts which have been left on disk.
 */
class ClearOldKeys extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deployer:purge-temp';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clears out any temp SSH key files and wrapper scripts.';

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * ClearOldKeys constructor.
     *
     * @param Filesystem $filesystem
     */
    public function __construct(Filesystem $filesystem)
    {
        parent::__construct();

        $this->filesystem = $filesystem;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $tmp_dir = storage_path('app/tmp/');

        // Clear out old SSH key files and archives
        $keys     = $this->filesystem->glob($tmp_dir . '*key*');
        $tmp      = $this->filesystem->glob($tmp_dir . '*tmp*');
        $archives = $this->filesystem->glob(storage_path('app/') . '*.tar.gz');

        $files   = array_merge($keys, $archives, $tmp_dir);
        $folders = $this->filesystem->glob($tmp . 'clone_*'); // cloned copies of code

        $this->info('Found ' . count($files) . ' files and ' . count($folders) . ' folders to purge');

        // Now loop through the temp files and delete them from storage
        foreach (array_merge($files, $folders) as $path) {
            $file = $this->filesystem->basename($path);

            // Don't delete recently created files as a precaution, 12 hours is more than enough
            if ($this->filesystem->lastModified($path) > strtotime('-12 hours')) {
                $this->info('Skipping ' . $file);
                continue;
            }

            $success = true;

            if ($this->filesystem->isDirectory($path)) {
                if (!$this->filesystem->deleteDirectory($path)) {
                    $this->error('Failed to delete folder ' . $file);
                    $success = false;
                }
            } elseif (!$this->filesystem->delete($path)) {
                $this->error('Failed to delete file ' . $file);
                $success = false;
            }

            if ($success) {
                $this->info('Deleted ' . $file);
            }
        }
    }
}
