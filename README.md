# Laravel State Machine

A Laravel 11+ package that provides a fluent state machine implementation without requiring Eloquent models. Inspired by Spatie's `laravel-model-states` but designed to be model-agnostic.

## Installation

```bash
composer require crumbls/state-machine
```

## Basic Usage

### 1. Define Your States

```php
<?php

use Crumbls\StateMachine\State;
use Crumbls\StateMachine\StateConfig;

abstract class OrderState extends State
{
    abstract public function color(): string;
    
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Pending::class)
            ->allowTransition(Pending::class, Processing::class)
            ->allowTransition(Processing::class, Shipped::class)
            ->allowTransition(Processing::class, Cancelled::class)
            ->allowTransition(Shipped::class, Delivered::class)
            ->allowTransition(Pending::class, Cancelled::class);
    }
}

class Pending extends OrderState
{
    public function color(): string
    {
        return 'yellow';
    }
}

class Processing extends OrderState
{
    public function color(): string
    {
        return 'blue';
    }
}

// ... other state classes
```

### 2. Create and Use State Machine

```php
use Crumbls\StateMachine\StateMachine;

// Create a new state machine
$machine = StateMachine::make(OrderState::class);

// Check current state
$machine->is(Pending::class); // true

// Check if transition is allowed
$machine->canTransitionTo(Processing::class); // true

// Transition to new state
$machine->transitionTo(Processing::class);

// Access state methods
$machine->getCurrentState()->color(); // 'blue'
```

### 3. Working with Context

```php
// Create with initial context
$machine = StateMachine::make(OrderState::class, [
    'order_id' => 123,
    'user_id' => 456
]);

// Add context during transition
$machine->transitionTo(Processing::class, [
    'processed_at' => now(),
    'processor_id' => auth()->id()
]);

// Access context
$context = $machine->getContext();
```

### 4. Using the Manager

```php
use Crumbls\StateMachine\StateMachineManager;

$manager = app(StateMachineManager::class);

// Create named state machine
$machine = $manager->create('order-123', OrderState::class);

// Retrieve later
$machine = $manager->get('order-123');

// Or use facade
$machine = StateMachine::create('order-123', OrderState::class);
```

## Advanced Features

### Middleware

Add Laravel-style middleware to state transitions for security, validation, and performance monitoring:

```php
use Crumbls\StateMachine\Middleware\RateLimitMiddleware;
use Crumbls\StateMachine\Middleware\ValidationMiddleware;
use Crumbls\StateMachine\Middleware\TimingMiddleware;

public static function config(): StateConfig
{
    return parent::config()
        ->default(Pending::class)
        ->allowTransition(Pending::class, Processing::class)
        ->middleware([
            // Rate limiting: max 5 attempts per minute
            RateLimitMiddleware::perMinutes(5, 1),
            
            // Validation: ensure required context
            ValidationMiddleware::rules([
                'user_id' => 'required|integer',
                'payment_confirmed' => 'required|boolean'
            ]),
            
            // Performance monitoring
            TimingMiddleware::maxExecutionTime(2000) // 2 seconds max
        ]);
}
```

**Available Middleware:**

- `RateLimitMiddleware`: Prevents too many transition attempts
- `ValidationMiddleware`: Validates context data using Laravel validation rules  
- `ThrottleMiddleware`: Temporarily blocks transitions after failures
- `TimingMiddleware`: Monitors and limits execution time
- `LoggingMiddleware`: Comprehensive transition logging

**Custom Middleware:**

Create your own middleware using Laravel's standard pattern:

```php
class CustomAuthMiddleware
{
    public function handle(StateTransitionRequest $request, Closure $next)
    {
        if (!auth()->check()) {
            throw new UnauthorizedException('Authentication required');
        }
        
        // Add user info to context
        $request->mergeContext(['authenticated_user' => auth()->id()]);
        
        return $next($request);
    }
}

// Use in config
->middleware([CustomAuthMiddleware::class])
```

### Guards

Add conditional logic to transitions:

```php
public static function config(): StateConfig
{
    return parent::config()
        ->default(Pending::class)
        ->allowTransition(Pending::class, Processing::class)
        ->guard(Pending::class, Processing::class, function ($state, $context) {
            return isset($context['payment_confirmed']) && $context['payment_confirmed'];
        });
}
```

### Callbacks

Add hooks for state transitions:

```php
public static function config(): StateConfig
{
    return parent::config()
        ->default(Pending::class)
        ->allowTransition(Pending::class, Processing::class)
        ->onTransition(Pending::class, Processing::class, function ($state, $context) {
            // Log transition
            Log::info('Order processing started', $context);
        })
        ->onEnter(Processing::class, function ($state, $context) {
            // Send notification
            Notification::send($context['user'], new OrderProcessingNotification());
        });
}
```

### Events

The package fires Laravel events on state transitions:

```php
use Crumbls\StateMachine\Events\StateTransitioned;
use Crumbls\StateMachine\Events\AsyncTransitionCompleted;
use Crumbls\StateMachine\Events\AsyncTransitionFailed;

Event::listen(StateTransitioned::class, function ($event) {
    // $event->stateMachine
    // $event->fromState
    // $event->toState
    // $event->context
});

Event::listen(AsyncTransitionCompleted::class, function ($event) {
    // $event->stateMachine
    // $event->fromState
    // $event->toState
    // $event->identifier
    // $event->context
});
```

## Async Transitions

Process state transitions in the background using Laravel queues:

### Basic Async Transition

```php
// Queue a state transition
$job = $machine->transitionToAsync(Processing::class, ['note' => 'async processing']);

// With custom queue and delay
$job = $machine->transitionToAsync(
    Processing::class,
    ['note' => 'processing'],
    'order-123',           // identifier
    'state-transitions',   // queue name
    60                     // delay in seconds
);
```

### Async with Auto-Continuation

Automatically continue to the next state when there's only one option:

```php
$job = $machine->transitionToAsyncWithContinuation(
    Processing::class,
    ['note' => 'will auto-continue'],
    'order-123',
    'state-transitions',
    0,
    function ($machine, $from, $to) {
        // Success callback
        Log::info("Transitioned from {$from} to {$to}");
    },
    function ($exception, $toState, $context) {
        // Failure callback
        Log::error("Failed to transition to {$toState}: " . $exception->getMessage());
    }
);
```

## Model Integration

Use the `HasStateMachine` trait for seamless Eloquent model integration:

```php
use Crumbls\StateMachine\Traits\HasStateMachine;

class Order extends Model
{
    use HasStateMachine;

    protected $fillable = ['state_machine_data', 'customer_id'];

    public function getStateMachineClass(): string
    {
        return OrderState::class;
    }
}

// Usage
$order = Order::create(['customer_id' => 123]);

// Sync transition
$order->transitionTo(Processing::class);

// Async transition
$order->transitionToAsync(Processing::class, ['note' => 'processing async']);

// Check state
$order->isInState(Processing::class); // true
$order->getCurrentState()->color(); // 'blue'

// With model-based jobs
class ProcessOrderJob implements ShouldQueue
{
    public function __construct(public Order $order) {}
    
    public function handle()
    {
        // The state machine data is automatically saved/loaded
        $this->order->transitionTo(Shipped::class);
    }
}
```

## Testing

Run the test suite:

```bash
composer test
```

## Requirements

- PHP 8.2+
- Laravel 11+

## License

MIT License