# BbCo AI Provider

A proxy/router AI provider that delegates requests to configured real AI providers (OpenAI, Azure AI, Ollama, etc.).

## Overview

The BbCo AI provider is not a real AI provider by itself. Instead, it acts as a middleware layer that:

1. **Validates** that at least one real AI provider is configured
2. **Discovers** enabled and configured AI provider instances dynamically
3. **Orders** instances using the broker preference and priority configuration
4. **Delegates** a fresh cloned action to each selected provider
5. **Falls back** only after recoverable 5xx responses
6. **Overrides** the effective `generate_text` system instruction for callers that provide a request-specific instruction

Client errors, including 429, and processor exceptions are terminal. The broker applies its own Moodle AI rate limit; the effective provider applies its limit when delegated. Request-specific instructions replace the effective provider's configured `generate_text` system instruction on a request-local provider copy; the configured provider instance is never mutated.

## Requirements

- Moodle 5.1 (`2025100600`).
- PHP 8.2 or newer, following the Moodle 5.1 requirement.

## Deployment with local_parce

For the matching `2026080800` development pair, install and configure BBCO before installing `local_parce`. Configure at least one real provider with the `generate_text` action enabled.

## Installation

Download zip package, extract the bbco folder and upload this folder into public/ai/provider.

## ABOUT
* **Developed by:** David Herney - david dot herney at bambuco dot co
* **GIT:** https://github.com/bambuco/moodle-aiprovider_bbco
* **Powered by:** [BambuCo](https://bambuco.co/)

## License

GNU GPL v3 or later

## Author

David Herney @ BambuCo (2026)
