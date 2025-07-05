# Bulletproof Examples

This directory contains battle-tested, production-ready examples of the `crumbls/state-machine` package.

## 🛒 E-commerce Order Example (`ECommerceOrder.php`)

A comprehensive order processing system demonstrating:

### Features
- **Complete order lifecycle** from payment to delivery
- **Rate limiting** (10 transitions per minute)
- **Throttling** (5 failed attempts = 5 minute lockout)
- **Validation** (required user_id, order_total)
- **Business logic guards** (inventory, packing, delivery confirmation)
- **Automatic side effects** (inventory management, notifications)
- **Async processing** with continuation
- **Database queries** with state-based scopes

### States
- `PendingPayment` → `PaymentConfirmed` → `Processing` → `Shipped` → `Delivered` → `Completed`
- Error states: `PaymentFailed`, `Cancelled`, `OnHold`
- Return flow: `ReturnRequested` → `ReturnApproved` → `Refunded`

### Usage
```php
$order = Order::create(['user_id' => 123, 'order_total' => 99.99]);

// Sync operations
$order->confirmPayment(['method' => 'credit_card']);
$order->startProcessing();
$order->ship('TRACK123');
$order->markDelivered();

// Async operations
$order->processAsync('high-priority-queue');

// Queries
$pendingOrders = Order::pendingProcessing()->get();
$activeOrders = Order::activeOrders()->get();
```

## 📄 Document Workflow Example (`DocumentWorkflow.php`)

A multi-stage document approval system demonstrating:

### Features
- **Role-based permissions** (author, reviewer, manager, executive)
- **Value-based escalation** ($10k reviewer, $100k manager, $100k+ executive)
- **Timeout management** with deadlines
- **Comprehensive audit trails**
- **Performance monitoring** (slow transition detection)
- **Automatic escalation** based on business rules

### States
- `Draft` → `SubmittedForReview` → `UnderReview` → `ApprovedByReviewer`
- Manager approval: `PendingManagerApproval` → `UnderManagerReview` → `ApprovedByManager`
- Executive review: `Escalated` → `UnderExecutiveReview` → `FinalApproved`
- Rejection flow: `ChangesRequested`, `RejectedByReviewer`, `RejectedByManager`, `FinalRejected`

### Usage
```php
$document = Document::create([
    'title' => 'Contract Amendment',
    'author_id' => 123,
    'document_value' => 50000
]);

// Workflow operations
$document->submitForReview(123, 'author');
$document->startReview(456, 'reviewer');
$document->approve(456, 'reviewer', ['Looks good']);
$document->approve(789, 'manager', ['Approved for execution']);

// Utility methods
$isOverdue = $document->isOverdue();
$daysLeft = $document->getDaysRemaining();
$needsAction = $document->isAwaitingAction('manager');

// Queries
$managerTasks = Document::awaitingRole('manager')->get();
$overdueDocuments = Document::overdue()->get();
```

## 🛡️ Battle-Proof Features

Both examples demonstrate:

### Security
- **Input validation** with detailed error messages
- **Role-based access control** via guards
- **Rate limiting** to prevent abuse
- **Throttling** with exponential backoff

### Performance
- **Timing monitoring** with slow query detection
- **Memory usage tracking**
- **Efficient database queries** with JSON state extraction
- **Async processing** for expensive operations

### Reliability
- **Comprehensive error handling** with specific exceptions
- **Audit trails** for compliance and debugging
- **Automatic retries** with circuit breaker patterns
- **Graceful degradation** when services are unavailable

### Monitoring
- **Detailed logging** at every step
- **Performance metrics** collection
- **Business event tracking**
- **Error aggregation** and alerting

## 🚀 Production Deployment

### Database Migrations
```php
// Orders table
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id');
    $table->decimal('order_total', 10, 2);
    $table->json('state_machine_data')->nullable();
    $table->string('tracking_number')->nullable();
    $table->timestamp('delivered_at')->nullable();
    $table->timestamps();
    
    $table->index(['user_id']);
    $table->index([DB::raw("(JSON_EXTRACT(state_machine_data, '$.current_state'))")]);
});

// Documents table
Schema::create('documents', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->text('content');
    $table->unsignedBigInteger('author_id');
    $table->decimal('document_value', 12, 2)->default(0);
    $table->json('state_machine_data')->nullable();
    $table->timestamp('deadline')->nullable();
    $table->unsignedBigInteger('reviewer_id')->nullable();
    $table->unsignedBigInteger('manager_id')->nullable();
    $table->timestamps();
    
    $table->index(['author_id']);
    $table->index(['deadline']);
    $table->index([DB::raw("(JSON_EXTRACT(state_machine_data, '$.current_state'))")]);
});
```

### Service Providers
Register required services in your `AppServiceProvider`:

```php
// In boot() method
$this->app->singleton('inventory.service', InventoryService::class);
$this->app->singleton('warehouse.service', WarehouseService::class);
$this->app->singleton('notification.service', NotificationService::class);
$this->app->singleton('payment.service', PaymentService::class);
$this->app->singleton('audit.service', AuditService::class);
$this->app->singleton('assignment.service', AssignmentService::class);
$this->app->singleton('deadline.service', DeadlineService::class);
$this->app->singleton('document.service', DocumentService::class);
```

### Queue Configuration
Configure your queues for optimal performance:

```php
// config/queue.php
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 300,
        'block_for' => 5,
    ],
],

'batching' => [
    'database' => env('DB_CONNECTION', 'mysql'),
    'table' => 'job_batches',
],
```

### Monitoring & Alerting
Set up monitoring for:
- Failed state transitions
- Slow transitions (>2 seconds)
- Rate limit violations
- Overdue documents/orders
- Queue backlogs

These examples are production-ready and handle real-world complexity while maintaining clean, maintainable code.