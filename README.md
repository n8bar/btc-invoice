# CryptoZing

**Invoice Bitcoin today - track payments on-chain.**

CryptoZing is a self-hosted Bitcoin invoicing app built for people who want clear USD-denominated invoices, straightforward Bitcoin payment flows, and reliable on-chain payment tracking.

Create an invoice in USD, present a live BTC quote and QR code your client can trust, assign a unique payment address, and let CryptoZing watch the chain for payment activity from send to settle. No manual checking. No guesswork. Just clean invoices, an easy payment path, and real on-chain signals.


## Why CryptoZing?

CryptoZing is built around a simple idea:

**Bitcoin invoicing should feel clear, modern, and dependable.**

That means:

- **USD-first quoting** so invoices stay familiar and readable
- **Live BTC pricing** so clients see a current quote
- **Unique invoice addresses** so payments are easier to attribute
- **On-chain tracking** so status updates reflect actual payment activity
- **Client-friendly delivery** with public links, QR codes, and receipts

## Core features

### Invoice Bitcoin with clarity
- Create invoices in USD
- Store a BTC snapshot alongside the invoice
- Show a BIP21 payment URI and scannable QR code
- Auto-generate invoice numbers
- Add due dates, notes, and branding overrides

### Track payments on-chain
- Assign a unique Bitcoin address to each invoice
- Watch invoice addresses for incoming payments
- Detect partial payments
- Track sats received, confirmations, and payment history
- Update invoice status automatically as payment activity arrives

### Make it easy for clients to pay
- Share public invoice links
- Rotate or disable public links at any time
- Support expiration windows for shared links
- Send invoice emails
- Auto-send receipts when invoices are paid

### Stay in control
- Connect a wallet account public key
- Validate wallet keys before saving
- Keep payment tracking tied to your own wallet lineage
- Review deliveries, alerts, and payment corrections inside the app

## Product highlights

- **Wallet-ready** - account public key support with on-chain watching
- **Client-friendly** - public links, QR payments, and receipts
- **Accurate** - USD-first quoting with partial payment awareness
- **On-chain aware** - payment visibility from send to settle
- **Delivery-ready** - email sends with status logging
- **Self-hostable** - run it on your own infrastructure

## How it works

1. Connect a wallet account public key
2. Create a client
3. Create an invoice in USD
4. CryptoZing derives a unique payment address for that invoice
5. Share the invoice using email or a public link
6. The client pays by scanning the QR code or using the Bitcoin URI
7. CryptoZing watches the chain, records payment activity, and updates invoice state

## Built for practical Bitcoin invoicing

CryptoZing is not trying to be a wallet.

It is an invoicing layer that helps you:

- quote cleanly
- collect cleanly
- verify cleanly
- communicate cleanly

The goal is a smoother path between **invoice created** and **invoice settled**.

## Stack

- **Laravel 12**
- **PHP 8.2+**
- **MySQL 8**
- **Blade + Alpine.js + Tailwind CSS**
- **Vite**
- **Laravel Sail / Docker Compose**
- **bitcoinjs-lib / bip32 / tiny-secp256k1**
- **simple-qrcode**

## Quick start

### Clone the repo

```bash
git clone https://github.com/n8bar/btc-invoice.git
cd btc-invoice
```

### Copy your environment file

```bash
cp .env.example .env
```

### Install dependencies

```bash
composer install
npm install
```

### Start the app with Sail

```bash
./vendor/bin/sail up -d
```

### Generate the app key and run migrations

```bash
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
```

### Build frontend assets

```bash
./vendor/bin/sail npm run build
```

Then open the app in your browser.

## Development

### Run the dev stack

```bash
composer run dev
```

### Run tests

```bash
./vendor/bin/sail artisan test
```

### Run the payment watcher

```bash
./vendor/bin/sail artisan wallet:watch-payments
```

## Wallet setup notes

CryptoZing expects an **account-level public key** for invoice receiving.

Important:

- Do **not** paste a seed phrase
- Prefer a **dedicated receiving account**
- CryptoZing derives invoice addresses from that account key
- Reusing the same account elsewhere can make payment attribution less reliable

## Email and sharing

CryptoZing supports queued email delivery for invoice sends, receipts, owner notices, and client alerts.

Public invoice links are tokenized and can be:

- enabled
- disabled
- rotated
- set to expire

## Project structure

```text
app/         Application code
config/      Laravel configuration
database/    Migrations and seeders
docs/        Product, ops, and milestone docs
public/      Public web root
resources/   Blade views and frontend assets
routes/      Web routes
tests/       Feature and unit tests
docker/      Container setup
```

## Who this is for

CryptoZing is for freelancers, builders, merchants, and Bitcoin-friendly operators who want a better way to send invoices and track payments without handing the whole flow to a custodial processor.

## Philosophy

CryptoZing aims to be:

- **clear for senders**
- **easy for clients**
- **grounded in real on-chain activity**
- **simple to self-host**

Invoice Bitcoin today. Track payments on-chain.

Remember: Not your keys, not your coins

## License

MIT
