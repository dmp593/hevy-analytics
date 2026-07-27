# Documentation

Start where your question is.

| I want to… | Read |
|---|---|
| Run this on my machine | [`DEVELOPMENT.md`](DEVELOPMENT.md) |
| Understand how the code is organised | [`ARCHITECTURE.md`](ARCHITECTURE.md) |
| Know what runs in production and why | [`PRODUCTION.md`](PRODUCTION.md) |
| Deploy it somewhere free | [`DEPLOY.md`](DEPLOY.md) |
| Set up the database, email, storage or payments | [`SERVICES.md`](SERVICES.md) |
| Understand the money | [`ECONOMICS.md`](ECONOMICS.md) |
| Know what the app promises and must keep proving | the root [`README.md`](../README.md) |

Two conventions worth knowing before reading any of it:

- **Comments explain decisions, not mechanics.** If a line of code says what it
  does, the comment above it says why it is that way rather than the obvious
  alternative — usually because the obvious alternative was tried and broke
  something. Those comments are the change log that matters.
- **Tests are arguments, not coverage.** Most guard a specific mistake that was
  made once. The docblock names it. If a test looks paranoid, its docblock
  explains what it caught.
