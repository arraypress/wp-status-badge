# Status Badge

Render a coloured pill for a status, without inventing the colours each time.

## What it does

Every admin screen ends up showing statuses — completed, pending, failed,
refunded — and every plugin picks its own greens and reds for them, so two
plugins on one site disagree about what amber means.

This maps a status name to a type, and the type to a badge. `completed` and
`active` are successes, `pending` and `on-hold` are warnings, `failed` and
`cancelled` are dangers. You pass the status you already have; the colour and
icon follow.

## Features

* Render a badge from a status name, with no colour decisions to make
* Get sensible defaults for the statuses WordPress and commerce plugins use
* Add your own statuses, or reassign one to a different type
* Ask whether a status is a success, warning, danger or info, for logic elsewhere
* Get a readable label from a machine status, so `on-hold` reads as "On hold"
* Override the label when the automatic one is not quite right

## Installation

```bash
composer require arraypress/wp-status-badge
```

## Quick start

In a list-table column:

```php
use ArrayPress\StatusBadge\StatusBadge;

$badges = new StatusBadge();

echo $badges->render( $order->status );
```

`completed` comes out green, `failed` red, `pending` amber — without the
calling code knowing which is which.

Adding a status the defaults do not cover:

```php
$badges = new StatusBadge( [
	'awaiting_stock' => 'warning',
] );
```

## Requirements

* PHP 8.3 or later
* WordPress 7.1 or later

## License

GPL-2.0-or-later
