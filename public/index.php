<?php
// SPDX-License-Identifier: GPL-2.0-only

/**
 * GitHub API bridge.
 *
 * Copyright (C) 2022 Alviro Iskandar Setiawan
 *
 * @author Alviro Iskandar Setiawan <alviro.iskandar@gnuweeb.org>
 * @version 0.0.1
 */

/*
 * Just a version identifier, doesn't really mean anything.
 */
const APP_VERSION = "0.0.1";

/*
 * The storage directory for cache saving.
 */
const STORAGE_DIR = __DIR__."/../storage";

/*
 * The lifetime of the cache in seconds.
 */
const CACHE_EXPIRE_TIME = 3600 * 5;

/*
 * The flags passed to the second argument of
 * json_encode() call.
 */
const JSON_ENCODE_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;

/**
 * @param string $fmt
 * @param mixed  ...$args
 * @return array
 */
function api_error(string $fmt, ...$args): array
{
	return ["error" => vsprintf($fmt, $args)];
}

/**
 * @param string $username
 * @param string $action
 * @param array  $data
 * @return void
 */
function save_cache(string $username, string $action, array $data): void
{
	$cache_file = sprintf("%s/%s.%s", STORAGE_DIR, $username, $action);
	$cache_data = [
		"expired_at" => (time() + CACHE_EXPIRE_TIME),
		"data"       => $data
	];

	/*
	 * Serialize the PHP array into a JSON string.
	 */
	$cache_data = json_encode($cache_data, JSON_UNESCAPED_SLASHES);

	/*
	 * Compress the cache with gzdeflate level 9.
	 *
	 * This saves us much storage since JSON string
	 * is often very compressible :-)
	 */
	$cache_data = gzdeflate($cache_data, 9);

	@file_put_contents($cache_file, $cache_data, LOCK_EX);
}

/**
 * @param int    &$http_code
 * @param string $username
 * @param string $action
 */
function fetch_from_github(int &$http_code, string $username, string $action): array
{
	$ua = sprintf("GitHub API bridge v%s", APP_VERSION);
	$endpoint = sprintf("https://api.github.com/users/%s%s", $username,
			    ($action === "_") ? "" : "/{$action}");

	$ch = curl_init($endpoint);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_USERAGENT, $ua);
	$out = curl_exec($ch);
	if (!$out) {
		$http_code = 503;
		$ern = curl_errno($ch);
		$err = curl_error($ch);
		return api_error("Curl error: (%d): %s", (int)$ern, (string)$err);
	}

	$info = curl_getinfo($ch);
	$out = json_decode($out, true);
	if ($info["http_code"] !== 200) {
		$http_code = $info["http_code"];
		return $out;
	}

	/*
	 * 200 OK, success!
	 */
	save_cache($username, $action, $out);
	return $out;
}

/**
 * @param string $username
 * @param string $action
 * @return ?array
 */
function open_from_cache(string $username, string $action): ?array
{
	$cache_file = sprintf("%s/%s.%s", STORAGE_DIR, $username, $action);

	if (!file_exists($cache_file))
		return NULL;

	$cache = @file_get_contents($cache_file, LOCK_EX);
	if (!$cache)
		return NULL;

	/*
	 * Decompress it.
	 */
	$cache = gzinflate($cache);
	if (!$cache)
		return NULL;

	/*
	 * Convert back to a PHP array.
	 */
	$cache = json_decode($cache, true);

	/*
	 * The cache JSON must contain "expired_at" and "data".
	 */
	if (!isset($cache["expired_at"], $cache["data"]))
		return NULL;

	if (!is_int($cache["expired_at"]) || !is_array($cache["data"]))
		return NULL;

	if (time() >= $cache["expired_at"]) {
		/*
		 * The cache file has been expired, delete it!
		 */
		@unlink($cache_file);
		return NULL;
	}

	return $cache["data"];
}

/**
 * @param int    &$http_code
 * @param string $username
 * @param string $action
 * @return array
 */
function do_action(int &$http_code, string $username, string $action): array
{
	if ($action === "")
		goto out;

	/*
	 * TODO: Support more actions...
	 */
	switch ($action) {
	case "_":
	case "repos":
		break;
	default:
		$http_code = 400;
		return api_error("Invalid action \"%s\"", $action);
	}

out:
	$res = open_from_cache($username, $action);
	if (!$res)
		$res = fetch_from_github($http_code, $username, $action);

	return $res;
}

/**
 * @return int
 */
function main(): int
{
	$http_code = 200;
	$res = [];

	if (!isset($_GET["username"])) {
		$http_code = 400;
		$res = api_error("Missing \"username\" query string");
		goto out;
	}

	if (!is_string($_GET["username"])) {
		$http_code = 400;
		$res = api_error("The \"username\" must be a string");
		goto out;
	}
	$username = $_GET["username"];

	if (!isset($_GET["action"])) {
		$action = "_";
	} else {
		if (!is_string($_GET["action"])) {
			$http_code = 400;
			$res = api_error("The \"action\" must be a string");
			goto out;
		}
		$action = $_GET["action"];
	}

	$res = do_action($http_code, $username, $action);
out:
	http_response_code($http_code);
	header("Content-Type: application/json");
	print(json_encode(
		[
			"code"     => $http_code,
			"response" => $res
		],
		JSON_ENCODE_FLAGS
	));
	return 0;
}

exit(main());
