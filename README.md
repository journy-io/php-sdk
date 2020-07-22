# php-sdk

Use https://github.com/thephpleague/skeleton for setting up this project

Requirements:
- Use PSR-18 interface for HTTP Client

```php
<?php

$client = new \JournyIO\Client($http, "apiKey");
$client->trackEvent($email, "trail started");
```
