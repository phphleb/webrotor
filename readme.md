[![License: MIT](https://img.shields.io/badge/License-MIT%20(Free)-brightgreen.svg)](https://github.com/phphleb/hleb/blob/master/LICENSE)
![PHP](https://img.shields.io/badge/PHP-^7.2-blue)
![PHP](https://img.shields.io/badge/PHP-8-blue)
[![build](https://github.com/phphleb/webrotor/actions/workflows/build.yml/badge.svg?event=push)](https://github.com/phphleb/webrotor/actions/workflows/build.yml)


<div align="center">
<h1>WebRotor&nbsp;</h1>
  <img src="https://raw.githubusercontent.com/phphleb/webrotor/d1af3115f767cce34ea80da697f4847ec0ae3db9/webrotor-300x300-logo.png" alt="WebRotor Logo" width="300">
</div><br>



<div align="center">
A web server designed to enable asynchronous request processing for shared hosting.
<br><br>
Supports PHP versions 7.2 and higher.
</div>



## Overview

Shared hosting environments often come with significant limitations compared to dedicated servers.  
However, those limitations shouldn't stop you from experimenting with multi-threaded and asynchronous servers!

**WebRotor** is a specialized web server designed for asynchronous request handling on shared hosting. It is easy to install and configure.  
*Important:* Before using, make sure to review the requirements for your code to execute asynchronously. Most frameworks include guidelines and recommendations for enabling asynchronous functionality.

With this library, applications built on frameworks like `Laravel`, `Symfony`, `Yii3`, `Slim`, and others that support asynchronous processing, along with any other asynchronously written code, can operate in this mode on various shared hosting platforms.

---

## Installation

Use [Composer](https://getcomposer.org/):


```bash
composer require phphleb/webrotor
```

Next, you need to install one of the PHP HTTP client implementations for PSR-7.

[Nyholm](https://github.com/Nyholm/psr7):

```bash
composer require nyholm/psr7
```
```bash
composer require nyholm/psr7-server
````

or [Guzzle](https://github.com/guzzle/guzzle/):

```bash
 composer require guzzlehttp/guzzle
````
### Modifying the Index File

Typically, dedicated hosting environments have a public folder containing an index file, usually named `index.php`.  
It is necessary to modify this file so that your application's code is enclosed within an asynchronous loop.
You can find examples of how to connect to an asynchronous server in the documentation for the framework you are using.  
In a simplified manner, this looks like:

```php
<?php
// Contents of your index.php file.
// Basic example for displaying the greeting line.

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Phphleb\WebRotor\WebRotor;
use Phphleb\WebRotor\Src\Handler\NyholmPsr7Creator;

require __DIR__ . '/../vendor/autoload.php';

$psr7Creator = new NyholmPsr7Creator(); // or GuzzlePsr7Creator()
$server = new WebRotor();
$server->init($psr7Creator);

$server->run(function(ServerRequestInterface $request, ResponseInterface $response) {
    // Here's your application code. //
    $response->getBody()->write('Hello World!');

    return $response;
});
```

### Launching Workers

Now for the fun part! :) Shared hosting is unlikely to allow you to daemonize processes, but we will use the built-in cron (or its equivalents).
First, find the section in your hosting admin panel named something like "Task Scheduler" or "crontab."
A simple example would be launching two workers, each with a lifespan of two minutes.
We'll use the same index file (`index.php`) as our worker, specifying the worker number.
The following example shows two workers that launch every two minutes with a one-minute offset.
The implementation can vary depending on your hosting settings, but if you encounter any issues, try launching just the first worker to start with, or contact your hosting support and show them this example.


```bash
# Runs the first command every two minutes
*/2 * * * * /usr/local/bin/php7.2 /my-project/public_html/index.php --id=1

# Runs the second command with a one minute delay after the first
1-59/2 * * * * /usr/local/bin/php7.2 /my-project/public_html/index.php --id=2
```

Workers can number more than two, and the restart time may vary based on specific load and available server resources.

### Configuration

In the previous example, we indicated that two workers are running with a two-minute interval between them.
Now, it’s essential to modify this in the web server settings, as it defaults to one worker restarting every minute.
Here's how to do that:


```php
// ... //
use Phphleb\WebRotor\Config;

$config = new Config();
$config->logLevel = 'warning'; // Logging level according to PSR-3.
$config->workerNum = 2; // Number of workers.
$config->workerLifetimeSec = 120; // Worker lifetime is two minutes.

$server = new WebRotor($config);
 // ... //
```
By default, web server logs are stored above the public directory in the `wr-logs` folder.

### Working with Sessions, Cookies, and Files in Asynchronous Mode

Asynchronous mode has its own specific characteristics since the request is handled by a worker within a single thread inside the standard loop.  
As a result, additional attributes are passed through a created object that implements the `ServerRequestInterface`.

```php
// ... //
use Psr\Http\Message\ServerRequestInterface;

$server->run(function(ServerRequestInterface $request, ResponseInterface $response) {
    // Example of assigning a session.
    $request->getAttribute('session')->set('session_name', 'value');
    // or
    $_SESSION['session_name'] = 'value';
    // An example of getting a value from a session.
    $sessionParam = $request->getAttribute('session')->get('session_name');
    // or
    $sessionParam = $_SESSION['session_name'];
    
    // An example of assigning and getting a Cookie value.
    $request->getAttribute('cookie')->set('cookie_name', 'value', []);
    $cookieParam = $request->getAttribute('cookie')->get('cookie_name');
    // or
    $cookieParam = $_COOKIE['cookie_name'];
    
    $files = $request->getUploadedFiles(); // An array of special objects.
    // or
    $files = $request->getAttribute('files') // Standard array $_FILES
    // or
    $files = $_FILES;
    
    return $response;
});
```

### Local Development

For local development, there's no need to modify the previous setup. Simply run the following command from the project's public directory:


```php
php index.php
```

This will initiate a single worker for the duration specified in the configuration.  
If the worker is not running or has been disabled, your project will still function, but requests will be processed in the standard, non-synchronous mode.
As a result, using workers is optional for local development.

----------

[![Telegram](https://img.shields.io/badge/-Telegram-black?color=white&logo=telegram&style=social)](https://t.me/phphleb)
