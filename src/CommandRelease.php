<?php // phpcs:disable WordPress
/**
 * Define the command `release`.
 *
 * @package wp-version-control
 */

namespace WPVersionControl;

use Composer\Command\BaseCommand;
use Composer\Util\ProcessExecutor;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Noodlehaus\Config;

/**
 * Define the command class.
 */
class CommandRelease extends BaseCommand {

	/**
	 * Configure our custom command.
	 */
	protected function configure() {
		// Set the base command name.
		$this->setName( 'release' );

		// Set the command description.
		$this->setDescription( 'Update version number of the project, create a new git tag, and deploy.' );

		// Add an argument to the command.
		$this->addArgument(
			'segment',
			InputArgument::REQUIRED,
			'Release level: major|minor|patch'
		);

		// Add an option for including the git message.
		$this->addOption(
			'message',
			null,
			InputOption::VALUE_OPTIONAL,
			'Git commit and tag message'
		);
	}

	/**
	 * Interact with the user to get missing options.
	 *
	 * @param InputInterface  $input  Instance of InputInterface.
	 * @param OutputInterface $output Instance of OutputInterface.
	 */
	protected function interact( InputInterface $input, OutputInterface $output ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$this->input  = $input;
		$this->output = $output;

		if ( empty( $input->getOption( 'message' ) ) ) {
			$message = $this->getIO()->ask( 'Git commit and tag message: ' );
			$this->input->setOption( 'message', $message );
		}
	}

	/**
	 * Execute the command.
	 *
	 * @throws \Exception On non-clean git status.
	 * @param InputInterface  $input  Input interface for getting command options.
	 * @param OutputInterface $output Output interface for echoing results.
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		$this->input  = $input;
		$this->output = $output;
		$this->root   = $this->process( 'git rev-parse --show-toplevel' ) ?? getcwd();

		if ( ! empty( $this->process( 'git status --porcelain' ) ) ) {
			throw new \Exception( 'Git isn\'t clean.' );
		}

		$segment = $this->input->getArgument( 'segment' );
		$message = $this->input->getOption( 'message' );

		$curr_version_arr = $this->get_version();
		$version_arr      = $curr_version_arr;

		switch ( $segment ) {
			case 'major':
				$version_arr[0]++;
				$version_arr[1] = 0;
				$version_arr[2] = 0;
				break;

			case 'minor':
				$version_arr[1]++;
				$version_arr[2] = 0;
				break;

			case 'patch':
				$version_arr[2]++;
				break;

			default:
				$this->error( $segment . ' is not one of major|minor|patch.' );
				return 1;
		}

		$version = implode( '.', $version_arr );

		$this->log( '📣 Updating version from <info>' . implode( '.', $curr_version_arr ) . '</info> to <info>' . $version . '</info>' );

		$result = 0;

		// Composer.
		$result += $this->updateJsonFile( $version, 'composer.json' );

		// Package.
		$result += $this->updateJsonFile( $version, 'package.json' );

		// Theme stylesheet.
		$result += $this->updateTextFile( $version, 'style.css' );

		// Theme SCSS stylesheet.
		$result += $this->updateTextFile( $version, 'src/sass/style.scss' );

		// Plugin base file.
		$result += $this->updateTextFile( $version, basename( $this->root ) . '.php' );

		// Git tag.
		$result += $this->createGitTag( $version, $message );

		return $result;
	}

	/**
	 * Gets the current version from the most recent git tag.
	 *
	 * @throws \Exception On unclean git pull.
	 * @return array Array of version by [ major, minor, patch ]
	 */
	protected function get_version() {
		$this->process( 'git checkout master' );

		// Update from our remote.
		if ( $this->process( 'git remote' ) ) {
			$this->process( 'git pull' );
		}

		// If we didn't get a clean git pull, abort.
		if ( ! empty( $this->process( 'git status --porcelain' ) ) ) {
			throw new \Exception( 'Git pull resulted in a non-clean status.' );
		}

		$git_describe = null;

		if ( ! empty( $this->process( 'git tag --list' ) ) ) {
			$git_describe = $this->process( 'git describe --abbrev=0 --tags' );
		}

		$curr_version_str = $git_describe ?: '0.0.0';

		$curr_version = explode( '.', $curr_version_str );

		if ( empty( $curr_version[1] ) ) {
			$curr_version[1] = 0;
		}

		if ( empty( $curr_version[2] ) ) {
			$curr_version[2] = 0;
		}

		$curr_version = array_map( 'intval', $curr_version );

		return $curr_version;
	}

	/**
	 * Update the version in a JSON file.
	 *
	 * @param string $version New version string.
	 * @param string $file    Name of the file to update.
	 * @return int            Error code from updating the file.
	 */
	protected function updateJsonFile( $version, $file ) {
		// If we don't have a package.json, abort.
		if ( ! file_exists( $this->root . '/' . $file ) ) {
			$this->log( $file . ' not found' );
			return 0;
		}

		$config = new Config( $this->root . '/' . $file );

		// If we didn't load data, abort.
		if ( empty( $config->all() ) ) {
			return 0;
		}

		$config['version'] = $version;

		$contents = json_encode( $config->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL;

		return $this->updateFile( $file, $contents );
	}

	/**
	 * Update the version in a text file like the style.css or plugin.php file.
	 *
	 * @param string $version New version string.
	 * @param string $file    Name of the file to update.
	 * @return int            Error code from updating the file.
	 */
	protected function updateTextFile( $version, $file ) {
		if ( ! file_exists( $this->root . '/' . $file ) ) {
			$this->log( $file . ' not found' );
			return 0;
		}

		$contents = file_get_contents( $this->root . '/' . $file );

		if ( is_null( $contents ) ) {
			return 0;
		}

		$contents = preg_replace( '/^([\/\*\s]*)Version:(\s*)(\d+\.\d+.\d+)$/', '$1Version:$2' . $version, $contents );

		return $this->updateFile( $file, $contents );
	}

	/**
	 * Update a file's contents.
	 *
	 * @param string $file     Relative path of the file.
	 * @param string $contents File's new contents.
	 * @return int             Error code from updating the file.
	 */
	protected function updateFile( $file, $contents ) {
		if ( ! file_exists( $this->root . '/' . $file ) ) {
			return null;
		}

		$this->log( 'Processing ' . $file );

		$bytes = file_put_contents( $this->root . '/' . $file, $contents );

		if ( false === $bytes ) {
			return 1;
		}

		// Add the file to git staging.
		exec( 'git add ' . $this->root . '/' . $file );

		return 0;
	}

	/**
	 * Create a new git commit and tag.
	 *
	 * @param string $version New version string.
	 * @param string $message Commit and tag message.
	 * @return int            Error code from tagging the release.
	 */
	protected function createGitTag( $version, $message ) {
		$this->log( '🎁 Releasing new git tag' );

		// Commit our file changes.
		$this->process( 'git commit --message="' . $message . '"' );

		// Tag the new release.
		$this->process( 'git tag --message="' . $message . '" ' . $version );

		// Push to our remote.
		if ( $this->process( 'git remote' ) ) {
			$this->process( 'git push --tags' );
		}

		return 0;
	}

	/**
	 * Run a shell command on the box and return its output.
	 *
	 * @throws \Exception On failed process.
	 * @param string $cmd Shell command to run.
	 * @return string     Result of the command.
	 */
	protected function process( $cmd ) {
		$process = new ProcessExecutor( $this->getIO() );
		$result  = $process->execute( $cmd, $output );

		if ( 0 !== $result ) {
			throw new \Exception( $cmd . ' returned non-zero exit code ' . $result, $result );
		}

		return trim( $output );
	}

	/**
	 * Write an error to the output.
	 *
	 * @param string $message Message to write as an error.
	 */
	protected function error( $message ) {
		$this->getIO()->writeError( '<error>' . PHP_EOL . PHP_EOL . '⚠️  ' . $message . PHP_EOL . '</error>' );
	}

	/**
	 * Write an log message to the output.
	 *
	 * @param string $message Message to write as a notice.
	 */
	protected function log( $message ) {
		$this->output->writeln( $message . PHP_EOL );
	}
}
