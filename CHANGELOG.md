# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

No tagged releases yet.

## [5.1.4] - 2026-09-05

### Changed
- Context preparation moved from `Context` to `TransitionListener`.

## [5.1.3] - 2026-09-05

### Added
- `TransitionHistory::useModel()` to register an extended history model.

## [5.1.2] - 2026-09-05

### Changed
- Storing callback now receives a `TransitionHistory` instance.
- Improved engine restoration for `TransitionHistory`.

## [5.1.1] - 2026-09-05

### Added
- A new `storing` callback for preparing the context right before it is
  persisted to storage.

## [5.1.0] - 2026-09-05

### Added
- Charge context is now validated.
- File(s) are allowed in user context; they are filtered out of the storable
  context and MUST be handled in a `saving` callback.

### Changed
- User context is now mutable.
- Restored engines are cached in the `HasWorkflow` trait.
- Smart serialization of the blueprint.
- Documented file uploads.

### Fixed
- Variadic call of the validator factory.

## [5.0.7] - 2026-09-04

### Fixed
- Instantiate `WorkflowBlueprint` with dependency injection.

## [5.0.6] - 2026-09-04

### Changed
- `WorkflowBlueprint` instances are resolved with dependency injection.

## [5.0.5] - 2026-09-02

### Changed
- Userdata is handled more carefully.

## [5.0.4] - 2026-09-01

### Fixed
- Applying `WorkflowObserver`.

## [5.0.3] - 2026-08-27

### Changed
- Transition history relations query with trashed models (`withTrashed`).

## [5.0.2] - 2026-08-19

### Changed
- Refactored authorization, validation and event dispatching.
- The event dispatcher and the validator factory are injected into
  `WorkflowObserver`.
- Improved validation.

## [5.0.1] - 2026-08-18

### Added
- Generic annotations for blueprint.

## [5.0.0] - 2026-08-13

### Changed
- Initial v5 release: rewrite on PHP 8.1 with enums only.