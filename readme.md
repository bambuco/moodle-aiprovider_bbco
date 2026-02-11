# BbCo AI Provider

A proxy/router AI provider that delegates requests to configured real AI providers (OpenAI, Azure AI, Ollama, etc.).

## Overview

The BbCo AI provider is not a real AI provider by itself. Instead, it acts as a middleware layer that:

1. **Validates** that at least one real AI provider is configured
2. **Discovers** available AI providers dynamically
3. **Routes** AI requests to the first available configured provider
4. **Delegates** the actual processing to that provider

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
