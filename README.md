# Payroll Line — History of Manual Adjustments

A proof of concept implementing the "History of Manual Adjustments to an Earning Line" coding assignment for Alcor OS, using **DDD + CQRS + Event Sourcing**.

## Business rules covered

- A payroll line's value is calculated automatically, but a specialist can add manual corrections (signed amount + mandatory comment).
- Corrections are append-only: never edited or silently deleted. A mistake is fixed by adding a new, compensating correction.
- Once a line has received at least one manual correction, automatic recalculation no longer affects it — even if the underlying source data changes later.
- The line's current value and the full audit history of corrections are always available.

`bin/payroll-demo.php` reproduces the exact worked example from the assignment (8 steps, `$1,000.00 → ... → $1,104.45`) end to end, including Event Sourcing rehydration and the technical log entry created by the rejected recalculation attempt in step 4.

## Requirements

- PHP >= 8.2 with `ext-bcmath`
- Composer

## Running it

```bash
composer install

# Walk through the assignment's worked example
composer demo        # same as: php bin/payroll-demo.php

# Run the test suite
composer test        # same as: vendor/bin/phpunit
```

## Architecture

```
src/
├── Domain/Salary/
│   ├── Aggregate/Salary.php          Aggregate root — write side, enforces the business rules above
│   ├── ValueObject/Money.php         Value Object (DECIMAL string + BCMath)
│   ├── SalaryRepositoryInterface.php          Repository interface — implemented in Infrastructure
│   └── Event/
│       ├── AutoPayrollAmountChanged.php   Automatic recalculation applied (baseAmount)
│       └── ManualAdjustmentAdded.php      Manual correction applied (adjustment, comment)
│
├── Application/
│   ├── Command/, Handler/            One command + handler per write operation
│   ├── Query/, ReadModel/            GetPayrollLineQuery → PayrollLineReadModel (+ repository interface)
│   ├── Projection/                   Updates the read model from domain events
│   └── Logging/                      RejectedRecalculationLogInterface interface + RejectedAutoRecalculation entry
│
├── Infrastructure/
│   ├── EventStore/                   PayrollLineEventStoreInterface interface + InMemoryPayrollLineEventStore + PayrollLineEvent (storage record)
│   ├── Repository/                   InMemorySalaryRepository, InMemoryPayrollLineReadModelRepository
│   └── Logging/                      InMemoryRejectedRecalculationLog
│
```

`Application` and `Domain` never import from `Infrastructure` — they depend on the three interfaces above, each with exactly one `InMemory...` implementation.

`Salary` is the consistency boundary: automatic recalculation no longer affects the line once a manual adjustment exists (the assignment's central rule), and — a defensive addition found while tracing the write/read call chain, not something the assignment states directly — a manual adjustment cannot be added before any automatic amount exists. It's reconstructed by replaying its event stream (`SalaryRepositoryInterface::load()`).

`PayrollLineReadModel` is a separate, independently updated read side — holding the latest automatic amount, the full manual-adjustment history, and the current value, maintained incrementally by `PayrollLineProjectionHandler` as domain events arrive rather than recomputed on each query. It never shares state or code paths with `Salary`.

The aggregate is named `Salary`, not `PayrollLine`/`EarningLine` (the assignment's own wording) — because what it actually models is an employee's salary amount and its corrections. The read side uses `PayrollLine` naming instead (`PayrollLineReadModel`), so the same concept appears under two different names across the write and read sides.

## Key assumptions and decisions

- **`Salary` references the employee by id (`employeeId: string`), it does not embed an `Employee` aggregate.** `Employee` is out of scope for this PoC, and even if it weren't, embedding it would pull the aggregate's boundary away from what it's actually responsible for — the salary line and its manual corrections — into unrelated employee data. Referencing by id keeps that boundary focused.
- **`Money` is a Value Object, not a plain string/float.** It encapsulates monetary arithmetic, decimal precision, and the rules for combining amounts in one place — the internal representation (currently a DECIMAL string via BCMath) can change later without touching `Salary` or any other caller.
- **Automatic calculation is out of scope.** The domain receives an already-calculated amount from an external calculator/service. `bin/payroll-demo.php` feeds in the assignment's own numbers directly, rather than generating random ones — the point of the demo is to prove the implementation matches the specification's worked example.
- **Two domain events, not one generic event with a `method` field.** `AutoPayrollAmountChanged` and `ManualAdjustmentAdded` are distinct classes; the class itself is the discriminator.
- **Domain events carry no `id`.** This PoC has no message bus, no at-least-once delivery, no idempotent-consumer scenario — the one real justification for minting an event id upfront. `(employeeId, version)` is sufficient within this PoC because each employee has a single ordered event stream.
- **There is no real database.** `InMemoryPayrollLineEventStore` holds events in a plain in-memory array for the lifetime of the process — it stands in for a future Event Store (e.g. a `payroll_events` table) without the domain model depending on which one is behind it. Nothing here is persisted between runs.
- **`PayrollLineEvent` simulates one row of a future `payroll_events` database table** — a uniform record shared by both event types, kept separate from the domain events themselves. Unlike the domain events, it carries an `id`: a database row conventionally gets a primary key regardless of whether the domain consumes it, which is a different concern from the "no id on domain events" point above.
- **Rejected automatic recalculations are not persisted as domain events.** Only state-changing events enter the Event Store — the audit requirement covers applied corrections, not rejected system attempts. Each rejection is still recorded separately in a technical log (`RejectedRecalculationLogInterface`), kept out of the aggregate's audit trail on purpose.
- **A manual adjustment requires a prior automatic amount.** Enforced in the aggregate (`hasBaseAmount`), not just assumed.
- **Rehydration validates event version sequence** and rejects gaps. This is a minimal integrity check for the event stream, not a business requirement — the assignment doesn't ask for it; it's cheap insurance against a corrupted or out-of-order stream.
- **One read-side store and one projection handler, not one per event type.** `PayrollLineProjectionHandler` dispatches internally by event type (mirroring how `Salary` itself dispatches), rather than being split into two classes — avoids unnecessary duplication for what is still a small read model.
- **`Application` and `Domain` depend only on interfaces they own, never on `Infrastructure` directly** (`Domain\Salary\SalaryRepositoryInterface`, `Application\ReadModel\PayrollLineReadModelRepositoryInterface`, `Application\Logging\RejectedRecalculationLogInterface`). Each currently has exactly one `InMemory...` implementation. The abstraction exists to keep dependency direction aligned with the architecture, not to introduce unnecessary implementations.
- **`PayrollLineEventStoreInterface` is also an interface, for a different reason than the three above.** Its only consumer, `InMemorySalaryRepository`, is itself in `Infrastructure` — so no Dependency Rule boundary is crossed either way. The interface exists purely so the storage backend (`InMemoryPayrollLineEventStore` today, a future `Database...` implementation later) can be swapped without touching `InMemorySalaryRepository`.
- **No transactional guarantee between appending events and clearing the aggregate's uncommitted events.** Acceptable for a PoC; a real system would need a Unit of Work, a transactional event store, or the Outbox pattern.

## Testing

60 tests, 129 assertions across `Salary`'s business rules (including the zero/negative-amount invariants), `Money` (including negative-zero normalization and sign checks), the event store's storage-record translation and optimistic concurrency check, both repositories, the projection handler, and the query/command handlers.

One test in particular, `PayrollLineWorkedExampleFlowTest`, replays the assignment's full worked example through the actual pipeline (commands → handlers → projection → read model → query) rather than against `Salary` directly — this is what proves "current value and full audit history" are visible through the mechanism a real caller would use, not just internally consistent inside the aggregate.

`ConcurrentSaveTest` covers the optimistic-concurrency guarantee: a second concurrent save for the same employee is rejected with `ConcurrencyException` without corrupting the stream, different employees never conflict with each other, and sequential saves for the same employee still work.
