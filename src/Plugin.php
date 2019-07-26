<?php // phpcs:disable WordPress
/**
 * Defines the Composer Plugin interface.
 *
 * @package wp-version-control
 */

namespace WPVersionControl;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\Capable;
use Composer\Script\Event;


/**
 * Defines the Plugin class.
 */
class Plugin implements PluginInterface, Capable {

	/**
	 * Instance of Composer.
	 *
	 * @var Composer
	 */
	protected $composer;

	/**
	 * Instance of IO interface.
	 *
	 * @var IOInterface
	 */
	protected $io;

	/**
	 * Called on Plugin activation.
	 *
	 * @param Composer    $composer Instance of Composer.
	 * @param IOInterface $io       Instance of the IO interface.
	 */
	public function activate( Composer $composer, IOInterface $io ) {
		$this->composer = $composer;
		$this->io       = $io;
	}

	/**
	 * Define the capabilities of the plugin
	 *
	 * @return array Array of `Capability type => Provider class`.
	 */
	public function getCapabilities() {
		return [
			'Composer\Plugin\Capability\CommandProvider' => __NAMESPACE__ . '\CommandProvider',
		];
	}
}
