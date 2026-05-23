# Run PetConnect Locally

Use this command when the user wants the local app running.

## Standard Dev Stack

Run:

```bash
composer run dev
```

This starts Laravel's dev server, queue listener, and Vite dev server through the Composer script.

## SSR Dev Stack

Run only when SSR behavior is needed:

```bash
composer run dev:ssr
```

## Frontend Only

Run only Vite when Laravel is already running separately:

```bash
npm run dev
```

## Report

Provide the local URL shown by the command output and note which processes are running.
