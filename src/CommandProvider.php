<?php // phpcs:disable WordPress
/**
 * Defines custom commands available to packages.
 *
 * @package wp-version-control
 */

namespace WPVersionControl;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;

/**
 * Defines our CommandProvider class.
 */
class CommandProvider implements CommandProviderCapability {

	/**
	 * Get the commands provided by the plugin.
	 *
	 * @return array List of command classes.
	 */
	public function getCommands() {
		return [
			new CommandRelease,
		];
	}
}
