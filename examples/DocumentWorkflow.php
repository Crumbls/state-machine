<?php

namespace Crumbls\StateMachine\Examples;

use Crumbls\StateMachine\State;
use Crumbls\StateMachine\StateConfig;
use Crumbls\StateMachine\Middleware\ValidationMiddleware;
use Crumbls\StateMachine\Middleware\LoggingMiddleware;
use Crumbls\StateMachine\Middleware\TimingMiddleware;
use Illuminate\Database\Eloquent\Model;
use Crumbls\StateMachine\Traits\HasStateMachine;

/**
 * Bulletproof Document Approval Workflow
 * 
 * This example demonstrates a multi-stage document approval system
 * with role-based permissions, timing constraints, and audit trails.
 */

abstract class DocumentState extends State
{
    abstract public function requiredRole(): ?string;
    abstract public function timeoutDays(): ?int;
    abstract public function isTerminal(): bool;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Draft::class)
            
            // Workflow transitions
            ->allowTransition(Draft::class, SubmittedForReview::class)
            ->allowTransition(Draft::class, Cancelled::class)
            
            ->allowTransition(SubmittedForReview::class, UnderReview::class)
            ->allowTransition(SubmittedForReview::class, Cancelled::class)
            
            ->allowTransition(UnderReview::class, ChangesRequested::class)
            ->allowTransition(UnderReview::class, ApprovedByReviewer::class)
            ->allowTransition(UnderReview::class, RejectedByReviewer::class)
            
            ->allowTransition(ChangesRequested::class, Draft::class)
            ->allowTransition(ChangesRequested::class, SubmittedForReview::class)
            ->allowTransition(ChangesRequested::class, Cancelled::class)
            
            ->allowTransition(ApprovedByReviewer::class, PendingManagerApproval::class)
            ->allowTransition(ApprovedByReviewer::class, FinalApproved::class) // Direct approval for small docs
            
            ->allowTransition(PendingManagerApproval::class, UnderManagerReview::class)
            ->allowTransition(PendingManagerApproval::class, Escalated::class)
            
            ->allowTransition(UnderManagerReview::class, ApprovedByManager::class)
            ->allowTransition(UnderManagerReview::class, RejectedByManager::class)
            ->allowTransition(UnderManagerReview::class, Escalated::class)
            
            ->allowTransition(ApprovedByManager::class, FinalApproved::class)
            ->allowTransition(Escalated::class, UnderExecutiveReview::class)
            
            ->allowTransition(UnderExecutiveReview::class, FinalApproved::class)
            ->allowTransition(UnderExecutiveReview::class, FinalRejected::class)
            
            // Middleware for security and performance
            ->middleware([
                // Validate user permissions and document state
                ValidationMiddleware::rules([
                    'user_id' => 'required|integer|min:1',
                    'user_role' => 'required|string|in:author,reviewer,manager,executive',
                    'document_id' => 'required|integer|min:1'
                ]),
                
                // Monitor performance of approval workflow
                TimingMiddleware::withSlowDetection(2000), // 2 second threshold
                
                // Comprehensive audit logging
                LoggingMiddleware::detailed()
            ])
            
            // Role-based guards
            ->guard(SubmittedForReview::class, UnderReview::class, function ($state, $context) {
                return in_array($context['user_role'], ['reviewer', 'manager', 'executive']);
            })
            
            ->guard(UnderReview::class, ApprovedByReviewer::class, function ($state, $context) {
                return $context['user_role'] === 'reviewer';
            })
            
            ->guard(PendingManagerApproval::class, UnderManagerReview::class, function ($state, $context) {
                return in_array($context['user_role'], ['manager', 'executive']);
            })
            
            ->guard(UnderManagerReview::class, ApprovedByManager::class, function ($state, $context) {
                return $context['user_role'] === 'manager';
            })
            
            ->guard(UnderExecutiveReview::class, FinalApproved::class, function ($state, $context) {
                return $context['user_role'] === 'executive';
            })
            
            // Automatic escalation based on document value
            ->guard(ApprovedByReviewer::class, FinalApproved::class, function ($state, $context) {
                $documentValue = $context['document_value'] ?? 0;
                return $documentValue < 10000; // Under $10k can be approved by reviewer
            })
            
            ->guard(ApprovedByManager::class, FinalApproved::class, function ($state, $context) {
                $documentValue = $context['document_value'] ?? 0;
                return $documentValue < 100000; // Under $100k can be approved by manager
            })
            
            // Audit trail callbacks
            ->onTransition(Draft::class, SubmittedForReview::class, function ($state, $context) {
                app('audit.service')->log('document_submitted', [
                    'document_id' => $context['document_id'],
                    'submitted_by' => $context['user_id'],
                    'submitted_at' => now()
                ]);
            })
            
            ->onEnter(UnderReview::class, function ($state, $context) {
                // Assign to reviewer and set deadline
                app('assignment.service')->assignToReviewer(
                    $context['document_id'],
                    $context['reviewer_id'] ?? null
                );
                
                app('deadline.service')->setDeadline(
                    $context['document_id'],
                    now()->addDays(3) // 3 day review deadline
                );
            })
            
            ->onEnter(FinalApproved::class, function ($state, $context) {
                // Notify stakeholders and publish document
                app('notification.service')->notifyApproval($context['document_id']);
                app('document.service')->publish($context['document_id']);
            })
            
            ->onEnter(FinalRejected::class, function ($state, $context) {
                // Notify rejection and archive
                app('notification.service')->notifyRejection(
                    $context['document_id'],
                    $context['rejection_reason'] ?? 'No reason provided'
                );
            });
    }
}

// Document States
class Draft extends DocumentState
{
    public function requiredRole(): ?string { return 'author'; }
    public function timeoutDays(): ?int { return null; }
    public function isTerminal(): bool { return false; }
}

class SubmittedForReview extends DocumentState
{
    public function requiredRole(): ?string { return 'reviewer'; }
    public function timeoutDays(): ?int { return 1; }
    public function isTerminal(): bool { return false; }
}

class UnderReview extends DocumentState
{
    public function requiredRole(): ?string { return 'reviewer'; }
    public function timeoutDays(): ?int { return 3; }
    public function isTerminal(): bool { return false; }
}

class ChangesRequested extends DocumentState
{
    public function requiredRole(): ?string { return 'author'; }
    public function timeoutDays(): ?int { return 7; }
    public function isTerminal(): bool { return false; }
}

class ApprovedByReviewer extends DocumentState
{
    public function requiredRole(): ?string { return 'manager'; }
    public function timeoutDays(): ?int { return 1; }
    public function isTerminal(): bool { return false; }
}

class PendingManagerApproval extends DocumentState
{
    public function requiredRole(): ?string { return 'manager'; }
    public function timeoutDays(): ?int { return 2; }
    public function isTerminal(): bool { return false; }
}

class UnderManagerReview extends DocumentState
{
    public function requiredRole(): ?string { return 'manager'; }
    public function timeoutDays(): ?int { return 5; }
    public function isTerminal(): bool { return false; }
}

class ApprovedByManager extends DocumentState
{
    public function requiredRole(): ?string { return 'executive'; }
    public function timeoutDays(): ?int { return 1; }
    public function isTerminal(): bool { return false; }
}

class RejectedByReviewer extends DocumentState
{
    public function requiredRole(): ?string { return null; }
    public function timeoutDays(): ?int { return null; }
    public function isTerminal(): bool { return true; }
}

class RejectedByManager extends DocumentState
{
    public function requiredRole(): ?string { return null; }
    public function timeoutDays(): ?int { return null; }
    public function isTerminal(): bool { return true; }
}

class Escalated extends DocumentState
{
    public function requiredRole(): ?string { return 'executive'; }
    public function timeoutDays(): ?int { return 2; }
    public function isTerminal(): bool { return false; }
}

class UnderExecutiveReview extends DocumentState
{
    public function requiredRole(): ?string { return 'executive'; }
    public function timeoutDays(): ?int { return 7; }
    public function isTerminal(): bool { return false; }
}

class FinalApproved extends DocumentState
{
    public function requiredRole(): ?string { return null; }
    public function timeoutDays(): ?int { return null; }
    public function isTerminal(): bool { return true; }
}

class FinalRejected extends DocumentState
{
    public function requiredRole(): ?string { return null; }
    public function timeoutDays(): ?int { return null; }
    public function isTerminal(): bool { return true; }
}

class Cancelled extends DocumentState
{
    public function requiredRole(): ?string { return null; }
    public function timeoutDays(): ?int { return null; }
    public function isTerminal(): bool { return true; }
}

/**
 * Document Model with Workflow Integration
 */
class Document extends Model
{
    use HasStateMachine;

    protected $fillable = [
        'title',
        'content',
        'author_id',
        'document_value',
        'state_machine_data',
        'deadline',
        'reviewer_id',
        'manager_id'
    ];

    protected $casts = [
        'document_value' => 'decimal:2',
        'deadline' => 'datetime'
    ];

    public function getStateMachineClass(): string
    {
        return DocumentState::class;
    }

    protected function getStateMachineContext(): array
    {
        return [
            'model_id' => $this->id,
            'model_type' => static::class,
            'document_id' => $this->id,
            'document_value' => $this->document_value,
            'author_id' => $this->author_id,
            'reviewer_id' => $this->reviewer_id,
            'manager_id' => $this->manager_id
        ];
    }

    // Workflow methods
    public function submitForReview(int $userId, string $userRole): void
    {
        $this->transitionTo(SubmittedForReview::class, [
            'user_id' => $userId,
            'user_role' => $userRole,
            'submitted_at' => now()->toISOString()
        ]);
        
        $this->save();
    }

    public function startReview(int $reviewerId, string $userRole): void
    {
        $this->reviewer_id = $reviewerId;
        
        $this->transitionTo(UnderReview::class, [
            'user_id' => $reviewerId,
            'user_role' => $userRole,
            'reviewer_id' => $reviewerId,
            'review_started_at' => now()->toISOString()
        ]);
        
        $this->save();
    }

    public function approve(int $userId, string $userRole, array $comments = []): void
    {
        $targetState = match($userRole) {
            'reviewer' => ApprovedByReviewer::class,
            'manager' => ApprovedByManager::class,
            'executive' => FinalApproved::class,
            default => throw new \InvalidArgumentException("Invalid role for approval: {$userRole}")
        };

        $this->transitionTo($targetState, [
            'user_id' => $userId,
            'user_role' => $userRole,
            'approved_by' => $userId,
            'approved_at' => now()->toISOString(),
            'approval_comments' => $comments
        ]);
        
        $this->save();
    }

    public function requestChanges(int $userId, string $userRole, string $reason, array $changes = []): void
    {
        $this->transitionTo(ChangesRequested::class, [
            'user_id' => $userId,
            'user_role' => $userRole,
            'requested_by' => $userId,
            'change_reason' => $reason,
            'requested_changes' => $changes,
            'requested_at' => now()->toISOString()
        ]);
        
        $this->save();
    }

    public function reject(int $userId, string $userRole, string $reason): void
    {
        $targetState = match($userRole) {
            'reviewer' => RejectedByReviewer::class,
            'manager' => RejectedByManager::class,
            'executive' => FinalRejected::class,
            default => throw new \InvalidArgumentException("Invalid role for rejection: {$userRole}")
        };

        $this->transitionTo($targetState, [
            'user_id' => $userId,
            'user_role' => $userRole,
            'rejected_by' => $userId,
            'rejection_reason' => $reason,
            'rejected_at' => now()->toISOString()
        ]);
        
        $this->save();
    }

    public function escalate(int $userId, string $userRole, string $reason): void
    {
        $this->transitionTo(Escalated::class, [
            'user_id' => $userId,
            'user_role' => $userRole,
            'escalated_by' => $userId,
            'escalation_reason' => $reason,
            'escalated_at' => now()->toISOString()
        ]);
        
        $this->save();
    }

    // Utility methods
    public function isAwaitingAction(string $userRole): bool
    {
        $currentState = $this->getCurrentState();
        return $currentState->requiredRole() === $userRole;
    }

    public function isOverdue(): bool
    {
        $currentState = $this->getCurrentState();
        $timeoutDays = $currentState->timeoutDays();
        
        if (!$timeoutDays || !$this->deadline) {
            return false;
        }
        
        return $this->deadline->isPast();
    }

    public function getDaysRemaining(): ?int
    {
        if (!$this->deadline) {
            return null;
        }
        
        return max(0, now()->diffInDays($this->deadline, false));
    }

    // Query scopes
    public function scopeAwaitingRole($query, string $role)
    {
        return $query->whereRaw("JSON_EXTRACT(state_machine_data, '$.current_state') IN (
            SELECT class_name FROM document_states WHERE required_role = ?
        )", [$role]);
    }

    public function scopeOverdue($query)
    {
        return $query->where('deadline', '<', now())
                    ->whereRaw("JSON_EXTRACT(state_machine_data, '$.current_state') NOT IN (
                        SELECT class_name FROM document_states WHERE is_terminal = 1
                    )");
    }

    public function scopeInProgress($query)
    {
        return $query->whereRaw("JSON_EXTRACT(state_machine_data, '$.current_state') NOT IN (?, ?, ?)", [
            FinalApproved::class,
            FinalRejected::class,
            Cancelled::class
        ]);
    }
}