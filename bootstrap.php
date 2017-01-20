<?php

use Vanilla\Addon;

if (!defined('APPLICATION')) exit();
/**
 * Bootstrap.
 *
 * @copyright 2009-2016 Vanilla Forums Inc.
 * @license http://www.opensource.org/licenses/gpl-2.0.php GNU GPL v2
 * @package Core
 * @since 2.0
 */

/**
 * Bootstrap Before
 *
 * This file gives developers the opportunity to hook into Garden before any
 * real work has been done. Nothing has been included yet, aside from this file.
 * No Garden features are available yet.
 */
if (file_exists(PATH_ROOT.'/conf/bootstrap.before.php')) {
    require_once PATH_ROOT.'/conf/bootstrap.before.php';
}

/**
 * Define Core Constants
 *
 * Garden depends on the presence of a certain base set of defines that allow it
 * to be aware of its own place within the system. These are conditionally
 * defined here, in case they've already been set by a zealous bootstrap.before.
 */

// Path to the primary configuration file.
if (!defined('PATH_CONF')) {
    define('PATH_CONF', PATH_ROOT.'/conf');
}

// Include default constants if none were defined elsewhere.
if (!defined('VANILLA_CONSTANTS')) {
    include(PATH_CONF.'/constants.php');
}

// Make sure a default time zone is set.
// Do NOT edit this. See config `Garden.GuestTimeZone`.
date_default_timezone_set('UTC');

// Make sure the mb_* functions are utf8.
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

// Include the core autoloader.
require_once __DIR__.'/vendor/autoload.php';

// Guard against broken cache files.
if (!class_exists('Gdn')) {
    // Throwing an exception here would result in a white screen for the user.
    // This error usually indicates the .ini files in /cache are out of date and should be deleted.
    exit("Class Gdn not found.");
}

// Set up the dependency injection container.
$dic = new \Garden\Container\Container();
Gdn::setContainer($dic);

$dic->setInstance('Garden\Container\Container', $dic)
    ->rule('Interop\Container\ContainerInterface')
    ->setAliasOf('Garden\Container\Container')

    // Cache
    ->rule('Gdn_Cache')
    ->setShared(true)
    ->setFactory(['Gdn_Cache', 'initialize'])
    ->addAlias('Cache')

    // Configuration
    ->rule('Gdn_Configuration')
    ->setShared(true)
    ->addAlias('Config')

    // AddonManager
    ->rule('Vanilla\\AddonManager')
    ->setShared(true)
    ->setConstructorArgs(
        [
            [
                Addon::TYPE_ADDON => ['/applications', '/plugins'],
                Addon::TYPE_THEME => '/themes',
                Addon::TYPE_LOCALE => '/locales'
            ],
            PATH_CACHE
        ]
    )
    ->addAlias('AddonManager')

    // ApplicationManager
    ->rule('Gdn_ApplicationManager')
    ->setShared(true)
    ->addAlias('ApplicationManager')

    // PluginManager
    ->rule('Gdn_PluginManager')
    ->setShared(true)
    ->addAlias('PluginManager')

    // ThemeManager
    ->rule('Gdn_ThemeManager')
    ->setShared(true)
    ->addAlias('ThemeManager')

    // Locale
    ->rule('Gdn_Locale')
    ->setShared(true)
    ->setConstructorArgs([new \Garden\Container\Reference(['Gdn_Configuration', 'Garden.Locale'])])
    ->addAlias('Locale')

    // Request
    ->rule('Gdn_Request')
    ->setShared(true)
    ->addCall('fromEnvironment')
    ->addAlias('Request')

    // Database.
    ->rule('Gdn_Database')
    ->setShared(true)
    ->setConstructorArgs([new \Garden\Container\Reference(['Gdn_Configuration', 'Database'])])
    ->addAlias('Database')

    ->rule('Gdn_DatabaseStructure')
    ->setClass('Gdn_MySQLStructure')
    ->setShared(true)
    ->addAlias(Gdn::AliasDatabaseStructure)
    ->addAlias('MySQLStructure')

    ->rule('Gdn_SQLDriver')
    ->setClass('Gdn_MySQLDriver')
    ->setShared(true)
    ->addAlias('Gdn_MySQLDriver')
    ->addAlias('MySQLDriver')
    ->addAlias(Gdn::AliasSqlDriver)

    ->rule('Identity')
    ->setClass('Gdn_CookieIdentity')
    ->setShared(true)

    ->rule('Gdn_Session')
    ->setShared(true)
    ->addAlias('Session')

    ->rule(Gdn::AliasAuthenticator)
    ->setClass('Gdn_Auth')
    ->setShared(true)

    ->rule('Gdn_Router')
    ->addAlias(Gdn::AliasRouter)
    ->setShared(true)

    ->rule('Gdn_Dispatcher')
    ->setShared(true)
    ->addAlias(Gdn::AliasDispatcher)

    ->rule('Gdn_Model')
    ->setShared(true)

    ->rule('Gdn_IPlugin')
    ->setShared(true)

    ->rule('Gdn_Slice')
    ->setShared(true)
    ->addAlias('Slice')

    ->rule('Gdn_Statistics')
    ->addAlias('Statistics')
    ->setShared(true)

    ->rule('Gdn_Regarding')
    ->setShared(true)

    ->rule('BBCodeFormatter')
    ->setClass('BBCode')
    ->setShared(true)

    ->rule('Smarty')
    ->setShared(true)

    ->rule('ViewHandler.tpl')
    ->setClass('Gdn_Smarty')
    ->setShared(true)

    ->rule('Gdn_Form')
    ->addAlias('Form')
    ;

spl_autoload_register([$dic->get('Vanilla\\AddonManager'), 'autoload']);

// Load default baseline Garden configurations.
Gdn::config()->load(PATH_CONF.'/config-defaults.php');

// Load installation-specific configuration so that we know what apps are enabled.
Gdn::config()->load(Gdn::config()->defaultPath(), 'Configuration', true);

/**
 * Bootstrap Early
 *
 * A lot of the framework is loaded now, most importantly the core autoloader,
 * default config and the general and error functions. More control is possible
 * here, but some things have already been loaded and are immutable.
 */
if (file_exists(PATH_CONF.'/bootstrap.early.php')) {
    require_once PATH_CONF.'/bootstrap.early.php';
}

Gdn::config()->caching(true);
debug(c('Debug', false));

setHandlers();

/**
 * Installer Redirect
 *
 * If Garden is not yet installed, force the request to /dashboard/setup and
 * begin installation.
 */
if (Gdn::config('Garden.Installed', false) === false && strpos(Gdn_Url::request(), 'setup') === false) {
    safeHeader('Location: '.Gdn::request()->url('dashboard/setup', true));
    exit();
}

/**
 * Extension Managers
 *
 * Now load the Addon, Application, Theme and Plugin managers into the Factory, and
 * process the application-specific configuration defaults.
 */

// Start the addons, plugins, and applications.
Gdn::addonManager()->startAddonsByKey(c('EnabledPlugins'), Addon::TYPE_ADDON);
Gdn::addonManager()->startAddonsByKey(c('EnabledApplications'), Addon::TYPE_ADDON);
Gdn::addonManager()->startAddonsByKey(array_keys(c('EnabledLocales', [])), Addon::TYPE_LOCALE);

$currentTheme = c(!isMobile() ? 'Garden.Theme' : 'Garden.MobileTheme', 'default');
Gdn::addonManager()->startAddonsByKey([$currentTheme], Addon::TYPE_THEME);

// Load the configurations for enabled addons.
foreach (Gdn::addonManager()->getEnabled() as $addon) {
    /* @var Addon $addon */
    if ($configPath = $addon->getSpecial('config')) {
        Gdn::config()->load($addon->path($configPath));
    }
}

// Re-apply loaded user settings.
Gdn::config()->overlayDynamic();

/**
 * Bootstrap Late
 *
 * All configurations are loaded, as well as the Application, Plugin and Theme
 * managers.
 */
if (file_exists(PATH_CONF.'/bootstrap.late.php')) {
    require_once PATH_CONF.'/bootstrap.late.php';
}

if (c('Debug')) {
    debug(true);
}

Gdn_Cache::trace(debug());

/**
 * Extension Startup
 *
 * Allow installed addons to execute startup and bootstrap procedures that they may have, here.
 */

// Bootstrapping.
foreach (Gdn::addonManager()->getEnabled() as $addon) {
    /* @var Addon $addon */
    if ($bootstrapPath = $addon->getSpecial('bootstrap')) {
        $bootstrapPath = $addon->path($bootstrapPath);
        include $bootstrapPath;
    }
}

// Plugins startup
Gdn::pluginManager()->start();

// Fire an event for plugins to modify the container.
Gdn::pluginManager()->callEventHandler($dic, 'Container', 'init');

/**
 * Locales
 *
 * Install any custom locales provided by applications and plugins, and set up
 * the locale management system.
 */

// Load the Garden locale system.
Gdn::locale();

require_once PATH_LIBRARY_CORE.'/functions.validation.php';

// Start Authenticators
Gdn::authenticator()->startAuthenticator();

/**
 * Bootstrap After
 *
 * After the bootstrap has finished loading, this hook allows developers a last
 * chance to customize Garden's runtime environment before the actual request
 * is handled.
 */
if (file_exists(PATH_ROOT.'/conf/bootstrap.after.php')) {
    require_once PATH_ROOT.'/conf/bootstrap.after.php';
}

// Include "Render" functions now - this way pluggables and custom confs can override them.
require_once PATH_LIBRARY_CORE.'/functions.render.php';

if (!defined('CLIENT_NAME')) {
    define('CLIENT_NAME', 'vanilla');
}
