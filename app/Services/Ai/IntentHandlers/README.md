# Intent handlers are inline

This folder is intentionally a placeholder.

There are no per-intent handler classes in the current design. The AI Database Assistant uses:

1. IntentClassifier to classify the question into one fixed allowed intent.
2. AiDatabaseAssistantService::dispatch() to route that intent to private methods.
3. Hardcoded Eloquent queries to read safe pharmacy data.

The LLM must never generate raw SQL from user input.

If the intent list grows significantly later, this can be refactored to class-per-intent handlers.
