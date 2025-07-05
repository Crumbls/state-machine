<?php

namespace Crumbls\StateMachine\Examples;

use Crumbls\StateMachine\State;
use Crumbls\StateMachine\StateConfig;
use Crumbls\StateMachine\Middleware\RateLimitMiddleware;
use Crumbls\StateMachine\Middleware\ValidationMiddleware;
use Crumbls\StateMachine\Middleware\LoggingMiddleware;
use Crumbls\StateMachine\Middleware\ThrottleMiddleware;
use Illuminate\Database\Eloquent\Model;
use Crumbls\StateMachine\Traits\HasStateMachine;

/**
 * Bulletproof E-commerce Order State Machine
 * 
 * This example demonstrates a production-ready order processing system
 * with comprehensive validation, rate limiting, and error handling.
 */

// Order States
abstract class OrderState extends State
{
    abstract public function color(): string;
    abstract public function description(): string;
    abstract public function allowedActions(): array;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(PendingPayment::class)
            
            // Define all possible transitions
            ->allowTransition(PendingPayment::class, PaymentConfirmed::class)
            ->allowTransition(PendingPayment::class, PaymentFailed::class)
            ->allowTransition(PendingPayment::class, Cancelled::class)
            
            ->allowTransition(PaymentConfirmed::class, Processing::class)
            ->allowTransition(PaymentConfirmed::class, Cancelled::class)
            
            ->allowTransition(Processing::class, Shipped::class)
            ->allowTransition(Processing::class, Cancelled::class)
            ->allowTransition(Processing::class, OnHold::class)
            
            ->allowTransition(Shipped::class, Delivered::class)
            ->allowTransition(Shipped::class, InTransit::class)
            ->allowTransition(Shipped::class, ReturnRequested::class)
            
            ->allowTransition(InTransit::class, Delivered::class)
            ->allowTransition(InTransit::class, ReturnRequested::class)
            
            ->allowTransition(Delivered::class, Completed::class)
            ->allowTransition(Delivered::class, ReturnRequested::class)
            
            ->allowTransition(ReturnRequested::class, ReturnApproved::class)
            ->allowTransition(ReturnRequested::class, ReturnDenied::class)
            
            ->allowTransition(ReturnApproved::class, Refunded::class)
            ->allowTransition(ReturnDenied::class, Delivered::class)
            
            ->allowTransition(OnHold::class, Processing::class)
            ->allowTransition(OnHold::class, Cancelled::class)
            
            // Middleware pipeline for bulletproof operation
            ->middleware([
                // Rate limiting: Max 10 transitions per minute per order
                RateLimitMiddleware::perMinutes(10, 1),
                
                // Throttling: Lockout after 5 failed attempts for 5 minutes
                ThrottleMiddleware::attempts(5, 300),
                
                // Validation: Ensure required context data
                ValidationMiddleware::rules([
                    'user_id' => 'required|integer|min:1',
                    'order_total' => 'required|numeric|min:0'
                ], [
                    'user_id.required' => 'User ID is required for order transitions',
                    'order_total.required' => 'Order total is required for processing'
                ]),
                
                // Comprehensive logging
                LoggingMiddleware::detailed()
            ])
            
            // Guards for business logic
            ->guard(PaymentConfirmed::class, Processing::class, function ($state, $context) {
                // Only process if inventory is available
                return isset($context['inventory_confirmed']) && $context['inventory_confirmed'] === true;
            })
            
            ->guard(Processing::class, Shipped::class, function ($state, $context) {
                // Only ship if all items are packed
                return isset($context['all_items_packed']) && $context['all_items_packed'] === true;
            })
            
            ->guard(Delivered::class, Completed::class, function ($state, $context) {
                // Only complete after delivery confirmation period
                $deliveredAt = $context['delivered_at'] ?? null;
                if (!$deliveredAt) return false;
                
                $confirmationPeriod = now()->subDays(7);
                return \Carbon\Carbon::parse($deliveredAt)->isBefore($confirmationPeriod);
            })
            
            // Callbacks for side effects
            ->onEnter(PaymentConfirmed::class, function ($state, $context) {
                // Reserve inventory when payment is confirmed
                app('inventory.service')->reserve($context['order_id']);
            })
            
            ->onEnter(Processing::class, function ($state, $context) {
                // Notify warehouse to prepare order
                app('warehouse.service')->prepareOrder($context['order_id']);
            })
            
            ->onEnter(Shipped::class, function ($state, $context) {
                // Send tracking info to customer
                app('notification.service')->sendTrackingInfo(
                    $context['user_id'], 
                    $context['tracking_number']
                );
            })
            
            ->onEnter(Cancelled::class, function ($state, $context) {
                // Release inventory and process refund
                app('inventory.service')->release($context['order_id']);
                if (isset($context['refund_required']) && $context['refund_required']) {
                    app('payment.service')->processRefund($context['order_id']);
                }
            });
    }
}

class PendingPayment extends OrderState
{
    public function color(): string { return '#FFA500'; }
    public function description(): string { return 'Waiting for payment confirmation'; }
    public function allowedActions(): array { return ['cancel', 'retry_payment']; }
}

class PaymentConfirmed extends OrderState
{
    public function color(): string { return '#00FF00'; }
    public function description(): string { return 'Payment confirmed, ready for processing'; }
    public function allowedActions(): array { return ['cancel', 'process']; }
}

class PaymentFailed extends OrderState
{
    public function color(): string { return '#FF0000'; }
    public function description(): string { return 'Payment failed'; }
    public function allowedActions(): array { return ['retry_payment', 'cancel']; }
}

class Processing extends OrderState
{
    public function color(): string { return '#0000FF'; }
    public function description(): string { return 'Order is being processed'; }
    public function allowedActions(): array { return ['ship', 'hold', 'cancel']; }
}

class OnHold extends OrderState
{
    public function color(): string { return '#FFFF00'; }
    public function description(): string { return 'Order is on hold'; }
    public function allowedActions(): array { return ['resume', 'cancel']; }
}

class Shipped extends OrderState
{
    public function color(): string { return '#800080'; }
    public function description(): string { return 'Order has been shipped'; }
    public function allowedActions(): array { return ['track', 'request_return']; }
}

class InTransit extends OrderState
{
    public function color(): string { return '#FFC0CB'; }
    public function description(): string { return 'Order is in transit'; }
    public function allowedActions(): array { return ['track', 'request_return']; }
}

class Delivered extends OrderState
{
    public function color(): string { return '#008000'; }
    public function description(): string { return 'Order has been delivered'; }
    public function allowedActions(): array { return ['complete', 'request_return']; }
}

class Completed extends OrderState
{
    public function color(): string { return '#006400'; }
    public function description(): string { return 'Order is completed'; }
    public function allowedActions(): array { return ['reorder']; }
}

class Cancelled extends OrderState
{
    public function color(): string { return '#8B0000'; }
    public function description(): string { return 'Order has been cancelled'; }
    public function allowedActions(): array { return ['reorder']; }
}

class ReturnRequested extends OrderState
{
    public function color(): string { return '#FF8C00'; }
    public function description(): string { return 'Return has been requested'; }
    public function allowedActions(): array { return ['approve_return', 'deny_return']; }
}

class ReturnApproved extends OrderState
{
    public function color(): string { return '#32CD32'; }
    public function description(): string { return 'Return has been approved'; }
    public function allowedActions(): array { return ['process_refund']; }
}

class ReturnDenied extends OrderState
{
    public function color(): string { return '#DC143C'; }
    public function description(): string { return 'Return has been denied'; }
    public function allowedActions(): array { return ['appeal_return']; }
}

class Refunded extends OrderState
{
    public function color(): string { return '#4169E1'; }
    public function description(): string { return 'Order has been refunded'; }
    public function allowedActions(): array { return ['reorder']; }
}

/**
 * Order Model with State Machine Integration
 */
class Order extends Model
{
    use HasStateMachine;

    protected $fillable = [
        'user_id',
        'order_total',
        'state_machine_data',
        'tracking_number',
        'delivered_at'
    ];

    protected $casts = [
        'order_total' => 'decimal:2',
        'delivered_at' => 'datetime'
    ];

    public function getStateMachineClass(): string
    {
        return OrderState::class;
    }

    protected function getStateMachineContext(): array
    {
        return [
            'model_id' => $this->id,
            'model_type' => static::class,
            'user_id' => $this->user_id,
            'order_total' => $this->order_total,
            'order_id' => $this->id,
            'tracking_number' => $this->tracking_number,
            'delivered_at' => $this->delivered_at?->toISOString()
        ];
    }

    // Business methods
    public function confirmPayment(array $paymentData = []): void
    {
        $this->transitionTo(PaymentConfirmed::class, array_merge([
            'payment_confirmed_at' => now()->toISOString(),
            'payment_method' => $paymentData['method'] ?? 'unknown'
        ], $paymentData));
        
        $this->save();
    }

    public function startProcessing(bool $inventoryConfirmed = true): void
    {
        $this->transitionTo(Processing::class, [
            'inventory_confirmed' => $inventoryConfirmed,
            'processing_started_at' => now()->toISOString()
        ]);
        
        $this->save();
    }

    public function ship(string $trackingNumber): void
    {
        $this->tracking_number = $trackingNumber;
        
        $this->transitionTo(Shipped::class, [
            'all_items_packed' => true,
            'tracking_number' => $trackingNumber,
            'shipped_at' => now()->toISOString()
        ]);
        
        $this->save();
    }

    public function markDelivered(): void
    {
        $this->delivered_at = now();
        
        $this->transitionTo(Delivered::class, [
            'delivered_at' => now()->toISOString(),
            'delivery_confirmed' => true
        ]);
        
        $this->save();
    }

    public function cancel(string $reason, bool $refundRequired = false): void
    {
        $this->transitionTo(Cancelled::class, [
            'cancelled_at' => now()->toISOString(),
            'cancellation_reason' => $reason,
            'refund_required' => $refundRequired
        ]);
        
        $this->save();
    }

    // Async processing methods
    public function processAsync(string $queue = 'orders'): void
    {
        $this->transitionToAsyncWithContinuation(
            Processing::class,
            ['inventory_confirmed' => true],
            $queue,
            0,
            function ($machine, $from, $to) {
                \Log::info("Order {$this->id} processed successfully", [
                    'from' => $from,
                    'to' => $to
                ]);
            },
            function ($exception, $toState, $context) {
                \Log::error("Order {$this->id} processing failed", [
                    'error' => $exception->getMessage(),
                    'target_state' => $toState
                ]);
            }
        );
    }

    // Query scopes
    public function scopeInState($query, string $stateClass)
    {
        return $query->whereRaw("JSON_EXTRACT(state_machine_data, '$.current_state') = ?", [$stateClass]);
    }

    public function scopePendingProcessing($query)
    {
        return $query->inState(PaymentConfirmed::class);
    }

    public function scopeActiveOrders($query)
    {
        return $query->whereRaw("JSON_EXTRACT(state_machine_data, '$.current_state') NOT IN (?, ?, ?)", [
            Completed::class,
            Cancelled::class,
            Refunded::class
        ]);
    }
}