# Payroll Line — History of Manual Adjustments

A proof of concept implementing the "History of Manual Adjustments to an Earning Line" coding assignment for Alcor OS, using **DDD + CQRS + Event Sourcing**.

## Business rules covered

- A payroll line's value is calculated automatically, but a specialist can add manual corrections (signed amount + mandatory comment).
- Corrections are append-only: never edited or silently deleted. A mistake is fixed by adding a new, compensating correction.
- Once a line has received at least one manual correction, automatic recalculation no longer affects it — even if the underlying source data changes later.
- The line's current value and the full audit history of corrections are always available.

`bin/payroll-demo.php` reproduces the exact worked example from the assignment (8 steps, `$1,000.00 → ... → $1,104.45`) end to end, including Event Sourcing rehydration and the technical log entry created by the rejected recalculation attempt in step 4.

## Requirements

- PHP >= 8.1 with `ext-bcmath`
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
│   ├── SalaryRepository.php          Repository interface — implemented in Infrastructure
│   └── Event/
│       ├── AutoPayrollAmountChanged.php   Automatic recalculation applied (baseAmount)
│       └── ManualAdjustmentAdded.php      Manual correction applied (adjustment, comment)
│
├── Application/
│   ├── Command/, Handler/            One command + handler per write operation
│   ├── Query/, ReadModel/            GetPayrollLineQuery → PayrollLineReadModel (+ repository interface)
│   ├── Projection/                   Updates the read model from domain events
│   └── Logging/                      RejectedRecalculationLog interface + RejectedAutoRecalculation entry
│
├── Infrastructure/
│   ├── EventStore/                   InMemoryPayrollLineEventStore + PayrollLineEvent (storage record)
│   ├── Repository/                   InMemorySalaryRepository, InMemoryPayrollLineReadModelRepository
│   └── Logging/                      InMemoryRejectedRecalculationLog
│
```

`Application` and `Domain` never import from `Infrastructure` — they depend on the three interfaces above, each with exactly one `InMemory...` implementation.

`Salary` is the consistency boundary: automatic recalculation no longer affects the line once a manual adjustment exists (the assignment's central rule), and — a defensive addition found while tracing the write/read call chain, not something the assignment states directly — a manual adjustment cannot be added before any automatic amount exists. It's reconstructed by replaying its event stream (`SalaryRepository::load()`).

`PayrollLineReadModel` is a separate, independently updated read side — holding the latest automatic amount, the full manual-adjustment history, and the current value, maintained incrementally by `PayrollLineProjectionHandler` as domain events arrive rather than recomputed on each query. It never shares state or code paths with `Salary`.

## Key assumptions and decisions

- **`Salary` references the employee by id (`employeeId: string`), it does not embed an `Employee` aggregate.** `Employee` is out of scope for this PoC, and even if it weren't, embedding it would pull the aggregate's boundary away from what it's actually responsible for — the salary line and its manual corrections — into unrelated employee data. Referencing by id keeps that boundary focused.
- **`Money` is a Value Object, not a plain string/float.** It encapsulates monetary arithmetic, decimal precision, and the rules for combining amounts in one place — the internal representation (currently a DECIMAL string via BCMath) can change later without touching `Salary` or any other caller.
- **Automatic calculation is out of scope.** The domain receives an already-calculated amount from an external calculator/service. `bin/payroll-demo.php` feeds in the assignment's own numbers directly, rather than generating random ones — the point of the demo is to prove the implementation matches the specification's worked example.
- **Two domain events, not one generic event with a `method` field.** `AutoPayrollAmountChanged` and `ManualAdjustmentAdded` are distinct classes; the class itself is the discriminator.
- **Domain events carry no `id`.** This PoC has no message bus, no at-least-once delivery, no idempotent-consumer scenario — the one real justification for minting an event id upfront. `(employeeId, version)` is already a sufficient natural key.
- **There is no real database.** `InMemoryPayrollLineEventStore` holds events in a plain in-memory array for the lifetime of the process — it stands in for a future Event Store (e.g. a `payroll_events` table) without the domain model depending on which one is behind it. Nothing here is persisted between runs.
- **`PayrollLineEvent` simulates one row of a future `payroll_events` database table** — a uniform record shared by both event types, kept separate from the domain events themselves. Unlike the domain events, it carries an `id`: a database row conventionally gets a primary key regardless of whether the domain consumes it, which is a different concern from the "no id on domain events" point above.
- **Rejected automatic recalculations are not persisted as domain events.** Only state-changing events enter the Event Store — the audit requirement covers applied corrections, not rejected system attempts. Each rejection is still recorded separately in a technical log (`RejectedRecalculationLog`), kept out of the aggregate's audit trail on purpose.
- **A manual adjustment requires a prior automatic amount.** Enforced in the aggregate (`hasBaseAmount`), not just assumed.
- **Rehydration validates event version sequence** and rejects gaps, guarding against a corrupted or out-of-order event stream.
- **One read-side store and one projection handler, not one per event type.** `PayrollLineProjectionHandler` dispatches internally by event type (mirroring how `Salary` itself dispatches), rather than being split into two classes — avoids unnecessary duplication for what is still a small read model.
- **`Application` and `Domain` depend only on interfaces they own, never on `Infrastructure` directly** (`Domain\Salary\SalaryRepository`, `Application\ReadModel\PayrollLineReadModelRepository`, `Application\Logging\RejectedRecalculationLog`). Each currently has exactly one `InMemory...` implementation — the point isn't swappability, it's the dependency direction: inner layers shouldn't import outer ones even when there's only one implementation to depend on.
- **No transactional guarantee between appending events and clearing the aggregate's uncommitted events.** Acceptable for a PoC; a real system would need a Unit of Work, a transactional event store, or the Outbox pattern.

## Testing

Unit tests cover the `Salary` aggregate and `Money` value object (every business rule, including the full assignment worked example replayed step by step), the technical log (`InMemoryRejectedRecalculationLog`), and `ApplyAutoCalculatedAmountCommandHandler`'s integration with it (a rejected recalculation is logged; an accepted one is not).

The event store's storage-record translation and the remaining command/query handlers are not covered by tests yet.
