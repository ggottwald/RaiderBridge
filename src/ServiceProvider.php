<?php

/**
 * This file is part of the TwigBridge package.
 *
 * @copyright Robert Crowe <hello@vivalacrowe.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace RaiderBridge;

use Illuminate\View\ViewServiceProvider;
use InvalidArgumentException;
use Raider\Lexer;
use Raider\Extension\DebugExtension;
use Raider\Extension\ExtensionInterface;
use Raider\Extension\EscaperExtension;
use Raider\Loader\ArrayLoader;
use Raider\Loader\ChainLoader;
use Twig_Environment;

/**
 * Bootstrap Laravel TwigBridge.
 *
 * You need to include this `ServiceProvider` in your app.php file:
 *
 * <code>
 *     'providers' => [
 *         'TwigBridge\ServiceProvider'
 *     ];
 * </code>
 */
class ServiceProvider extends ViewServiceProvider
{
    /**
     * {@inheritdoc}
     */
    public function register()
    {
        $this->registerCommands();
        $this->registerOptions();
        $this->registerLoaders();
        $this->registerEngine();
        $this->registerAliases();
    }

    /**
     * {@inheritdoc}
     */
    public function boot()
    {
        $this->loadConfiguration();
        $this->registerExtension();
    }

    /**
     * Check if we are running Lumen or not.
     *
     * @return bool
     */
    protected function isLumen()
    {
        return strpos($this->app->version(), 'Lumen') !== false;
    }

    /**
     * Check if we are running on PHP 7.
     *
     * @return bool
     */
    protected function isRunningOnPhp7()
    {
        return version_compare(PHP_VERSION, '7.0-dev', '>=');
    }

    /**
     * Load the configuration files and allow them to be published.
     *
     * @return void
     */
    protected function loadConfiguration()
    {
        $configPath = __DIR__ . '/../config/twigbridge.php';

        if (! $this->isLumen()) {
            $this->publishes([$configPath => config_path('twigbridge.php')], 'config');
        }

        $this->mergeConfigFrom($configPath, 'twigbridge');
    }

    /**
     * Register the Twig extension in the Laravel View component.
     *
     * @return void
     */
    protected function registerExtension()
    {
        $this->app['view']->addExtension(
            $this->app['raider.extension'],
            'raider',
            function () {
                return $this->app['raider.engine'];
            }
        );
    }

    /**
     * Register console command bindings.
     *
     * @return void
     */
    protected function registerCommands()
    {
        $this->app->bindIf('command.twig', function () {
            return new Command\TwigBridge;
        });

        $this->app->bindIf('command.twig.clean', function () {
            return new Command\Clean;
        });

        $this->app->bindIf('command.twig.lint', function () {
            return new Command\Lint;
        });

        $this->commands(
            'command.twig',
            'command.twig.clean',
            'command.twig.lint'
        );
    }

    /**
     * Register Twig config option bindings.
     *
     * @return void
     */
    protected function registerOptions()
    {
        $this->app->bindIf('raider.extension', function () {
            return $this->app['config']->get('twigbridge.twig.extension');
        });

        $this->app->bindIf('raider.options', function () {
            $options = $this->app['config']->get('twigbridge.twig.environment', []);

            // Check whether we have the cache path set
            if (! isset($options['cache']) || is_null($options['cache'])) {
                // No cache path set for Twig, lets set to the Laravel views storage folder
                $options['cache'] = storage_path('framework/views/twig');
            }

            return $options;
        });

        $this->app->bindIf('raider.extensions', function () {
            $load = $this->app['config']->get('twigbridge.extensions.enabled', []);

            // Is debug enabled?
            // If so enable debug extension
            $options = $this->app['raider.options'];
            $isDebug = (bool) (isset($options['debug'])) ? $options['debug'] : false;

            if ($isDebug) {
                array_unshift($load, DebugExtension::class);
            }

            return $load;
        });

        $this->app->bindIf('raider.lexer', function () {
            return null;
        });
    }

    /**
     * Register Twig loader bindings.
     *
     * @return void
     */
    protected function registerLoaders()
    {
        // The array used in the ArrayLoader
        $this->app->bindIf('raider.templates', function () {
            return [];
        });

        $this->app->bindIf('raider.loader.array', function ($app) {
            return new ArrayLoader($app['raider.templates']);
        });

        $this->app->bindIf('raider.loader.viewfinder', function () {
            return new Twig\Loader(
                $this->app['files'],
                $this->app['view']->getFinder(),
                $this->app['raider.extension']
            );
        });

        $this->app->bindIf(
            'raider.loader',
            function () {
                return new ChainLoader([
                    $this->app['raider.loader.array'],
                    $this->app['raider.loader.viewfinder'],
                ]);
            },
            true
        );
    }

    /**
     * Register Twig engine bindings.
     *
     * @return void
     */
    protected function registerEngine()
    {
        $this->app->bindIf(
            'raider',
            function () {
                $extensions = $this->app['raider.extensions'];
                $lexer = $this->app['raider.lexer'];
                $twig = new Bridge(
                    $this->app['raider.loader'],
                    $this->app['raider.options'],
                    $this->app
                );

                foreach ($this->app['config']->get('twigbridge.twig.safe_classes', []) as $safeClass => $strategy) {
                    $twig->getExtension(EscaperExtension::class)->addSafeClass($safeClass, $strategy);
                }

                // Instantiate and add extensions
                foreach ($extensions as $extension) {
                    // Get an instance of the extension
                    // Support for string, closure and an object
                    if (is_string($extension)) {
                        try {
                            $extension = $this->app->make($extension);
                        } catch (\Exception $e) {
                            throw new InvalidArgumentException(
                                "Cannot instantiate Twig extension '$extension': " . $e->getMessage()
                            );
                        }
                    } elseif (is_callable($extension)) {
                        $extension = $extension($this->app, $twig);
                    } elseif (! is_a($extension, ExtensionInterface::class)) {
                        throw new InvalidArgumentException('Incorrect extension type');
                    }

                    $twig->addExtension($extension);
                }

                // Set lexer
                if (is_a($lexer, Lexer::class)) {
                    $twig->setLexer($lexer);
                }

                return $twig;
            },
            true
        );

        $this->app->alias('raider', Twig_Environment::class);
        $this->app->alias('raider', Bridge::class);

        $this->app->bindIf('raider.compiler', function () {
            return new Engine\Compiler($this->app['raider']);
        });

        $this->app->bindIf('raider.engine', function () {
            return new Engine\Twig(
                $this->app['raider.compiler'],
                $this->app['raider.loader.viewfinder'],
                $this->app['config']->get('twigbridge.twig.globals', [])
            );
        });
    }

    /**
     * Register aliases for classes that had to be renamed because of reserved names in PHP7.
     *
     * @return void
     */
    protected function registerAliases()
    {
        if (! $this->isRunningOnPhp7() and ! class_exists('TwigBridge\Extension\Laravel\String')) {
            class_alias('TwigBridge\Extension\Laravel\Str', 'TwigBridge\Extension\Laravel\String');
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            'command.twig',
            'command.twig.clean',
            'command.twig.lint',
            'raider.extension',
            'raider.options',
            'raider.extensions',
            'raider.lexer',
            'raider.templates',
            'raider.loader.array',
            'raider.loader.viewfinder',
            'raider.loader',
            'raider',
            'raider.compiler',
            'raider.engine',
        ];
    }
}
