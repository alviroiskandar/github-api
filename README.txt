GitHub API Bridge
------------------

Hello!

This is the GitHub API bridge written in PHP. Inspired by Kirisaki Rem
<https://fb.com/3139496759598909>.

## Problem:
Using GitHub API directly in our application may result in getting
rate-limited due the large number of requests. This is not productive
and very inconvenience.

## Solution:
Create a GitHub API bridge that caches the API response into a file.
This can greatly reduce the number of HTTP requests since we're loading
the data from cache file instead of performing an HTTP request to
GitHub for every request.

## Storage Implementation Details:
1. Each request that gets a "200 OK" response is cached for 5 hours
   (this is configurable from CACHE_EXPIRE_TIME const in the index.php).

2. The cache file is compressed with gzdeflate level 9. This saves us
   much storage since JSON string is often very compressible.

3. No external database required.

## Usage:

   http://localhost/index.php?username=xxxxxx              # Show profile info
   http://localhost/index.php?username=xxxxxx&action=repos # Show user's repositories

## TODO List:
   - Support more actions.
   - Improve the caching mechanism.

## Project License:
This project is open-source under the GNU GPL v2 license.

## Contributing:
1. Via GitHub pull request at: https://github.com/alviroiskandar/github-api
2. Send patches to: Alviro Iskandar Setiawan <alviro.iskandar@gnuweeb.org>

-- 
2022-05-25
Alviro Iskandar Setiawan
