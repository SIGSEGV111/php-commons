<?php
declare(strict_types=1);
namespace phpcom\util;

final class KeyNotFoundException extends \Exception
{
	public function __construct(
		public readonly string $key,
		public readonly string $context = 'array',
		?\Throwable $previous = null
	)
	{
		$message = sprintf("Key '%s' not found in %s.", $key, $context);
		parent::__construct($message, 0, $previous);
	}
}

final class IoException extends \RuntimeException
{
	public function __construct(
		public readonly string $operation,
		public readonly string $resource = '',
		?\Throwable $previous = null
	) {
		$message = sprintf(
			"I/O operation '%s' failed%s.",
			$operation,
			$resource !== '' ? " on resource '{$resource}'" : ''
		);

		parent::__construct($message, 0, $previous);
	}

	/**
	* Helper for fopen() failures.
	*/
	public static function forOpen(string $filename, ?\Throwable $previous = null): self
	{
		return new self('fopen', $filename, $previous);
	}

	/**
	* Helper for fread() failures.
	*/
	public static function forRead(string $filename = '', ?\Throwable $previous = null): self
	{
		return new self('fread', $filename, $previous);
	}

	/**
	* Helper for fwrite() failures.
	*/
	public static function forWrite(string $filename = '', ?\Throwable $previous = null): self
	{
		return new self('fwrite', $filename, $previous);
	}
}

/**
 * @implements \IteratorAggregate<int, \SplFileInfo>
 */
final class DirectoryIterator implements \IteratorAggregate
{
	public string $root_dir;

	/** @var non-empty-string $filter_regex */
	public string $filter_regex;
	public bool $match_dirs;
	public bool $match_files;
	public bool $match_special;
	public int $max_depth;

	/** @param non-empty-string $filter_regex */
	public function __construct(
		string $root_dir,
		string $filter_regex = "/.*/",
		bool $match_dirs = true,
		bool $match_files = true,
		bool $match_special = true,
		int $max_depth = 1
	)
	{
		if(!is_dir($root_dir))
			throw new \InvalidArgumentException("Root directory '$root_dir' does not exist or is not a directory.");

		if(@preg_match($filter_regex, '') === false)
			throw new \InvalidArgumentException("Invalid regex '$filter_regex'.");

		/** @psalm-suppress UndefinedFunction */
		$this->root_dir = throwOnFalseOrNull(realpath($root_dir));
		$this->filter_regex = $filter_regex;
		$this->match_dirs = $match_dirs;
		$this->match_files = $match_files;
		$this->match_special = $match_special;
		$this->max_depth = $max_depth;
	}

	#[\Override]
	public function getIterator(): \Traversable
	{
		return $this->iterate($this->root_dir, 0);
	}

	private function iterate(string $dir, int $depth): \Generator
	{
		$entries = scandir($dir);
		if($entries === false)
			throw new \RuntimeException("Failed to read directory '$dir'.");

		foreach ($entries as $entry)
		{
			if($entry === '.' || $entry === '..')
				continue;

			$path = $dir . DIRECTORY_SEPARATOR . $entry;

			if(!file_exists($path))
				continue;

			$type = filetype($path);
			if($type === false)
				continue;

			$matches = false;
			switch ($type)
			{
				case 'dir':
					$matches = $this->match_dirs;
					break;
				case 'file':
					$matches = $this->match_files;
					break;
				default:
					$matches = $this->match_special;
					break;
			}

			if($matches && preg_match($this->filter_regex, $entry))
				yield $path;

			if($type === 'dir' && $depth < $this->max_depth || $this->max_depth < 0)
				yield from $this->iterate($path, $depth + 1);
		}
	}
}

function getEnvDefault(string $key, string $default) : string
{
	$value = getenv($key);
	return ($value === false) ? $default : $value;
}

function getEnvThrow(string $key) : string
{
	$value = getenv($key);
	if($value === false)
		throw new KeyNotFoundException($key, "environment");
	return $value;
}

function readFileThrow(string $file, bool $trim = false) : string
{
	$content = @file_get_contents($file);
	if($content === false)
		throw new IoException("file_get_contents", $file);
	if($trim)
		$content = trim($content);
	return $content;
}

function writeFile(string $file, string $content) : void
{
	if(file_put_contents($file, $content) === false)
		throw new IoException("file_put_contents", $file);
}

function readCsvFile(string $file, string $delimiter = ",", string $enclosure = '"'): array
{
	if(!is_readable($file))
		throw new \RuntimeException("File not readable: $file");

	$handle = fopen($file, "r");
	if($handle === false)
		throw new \RuntimeException("Failed to open file: $file");

	$data = [];
	while (($row = fgetcsv($handle, 0, $delimiter, $enclosure)) !== false)
		$data[] = $row;

	if(!feof($handle))
	{
		fclose($handle);
		throw new \RuntimeException("Error reading file: $file");
	}

	fclose($handle);
	return $data;
}

/**
 * @template T
 * @param T|null|false $value
 * @return T
 * @psalm-pure
 */
function throwOnFalseOrNull(mixed $value) : mixed
{
	if($value === null || $value === false)
		throw new \RuntimeException("value was false|null");
	return $value;
}

function ensureString(mixed $value) : string
{
	if(!is_string($value))
		throw new \RuntimeException("!is_string");
	return $value;
}
