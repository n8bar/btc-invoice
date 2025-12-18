#!/usr/bin/env node
const tinysecp = require('tiny-secp256k1');
const bip32Factory = require('bip32');
const bitcoin = require('bitcoinjs-lib');
const bs58check = require('bs58check').default;

const usage = () => {
  console.error('Usage: derive-address <xpub> <index> <network>');
  process.exit(1);
};

const [, , xpub, indexArg, networkArg = 'testnet'] = process.argv;
if (!xpub || typeof indexArg === 'undefined') {
  usage();
}

const index = Number.parseInt(indexArg, 10);
if (Number.isNaN(index) || index < 0) {
  console.error('Index must be a non-negative integer.');
  process.exit(1);
}

const bip32 = bip32Factory.BIP32Factory(tinysecp);

const VERSIONS = {
  // P2WPKH (BIP84) slip-132
  zpub: { public: 0x04b24746, private: 0x04b2430c, network: 'mainnet' },
  vpub: { public: 0x045f1cf6, private: 0x045f18bc, network: 'testnet' },
  // Default BIP32 (helps older exports)
  xpub: { public: 0x0488b21e, private: 0x0488ade4, network: 'mainnet' },
  tpub: { public: 0x043587cf, private: 0x04358394, network: 'testnet' },
};

const versionFromKey = (key) => {
  try {
    const data = Buffer.from(bs58check.decode(key));
    return data.readUInt32BE(0);
  } catch {
    throw new Error('Invalid xpub: checksum/version failed');
  }
};

const versionEntry = (version) => {
  return Object.values(VERSIONS).find(
    (entry) => entry.public === version || entry.private === version
  );
};

const NETWORK_ALIASES = {
  mainnet: 'mainnet',
  testnet: 'testnet',
  testnet3: 'testnet',
  testnet4: 'testnet',
};

const normalizeNetwork = (requested, derived) => {
  const requestedRaw = (requested ?? '').toString().trim().toLowerCase();
  const derivedRaw = (derived ?? '').toString().trim().toLowerCase();

  const requestedNormalized = requestedRaw
    ? (NETWORK_ALIASES[requestedRaw] || requestedRaw)
    : '';
  const derivedNormalized = derivedRaw
    ? (NETWORK_ALIASES[derivedRaw] || derivedRaw)
    : '';

  const normalized = requestedNormalized || derivedNormalized || 'testnet';
  if (!['mainnet', 'testnet'].includes(normalized)) {
    throw new Error(`Unknown network: ${requestedRaw || derivedRaw || normalized}`);
  }
  if (derivedNormalized && normalized !== derivedNormalized) {
    throw new Error(`Network mismatch: key is ${derivedRaw}, requested ${requestedRaw || normalized}`);
  }
  return normalized;
};

try {
  const version = versionFromKey(xpub);
  const entry = versionEntry(version);
  if (!entry) {
    throw new Error('Unsupported xpub format.');
  }

  const networkName = normalizeNetwork(networkArg, entry.network);
  const base = networkName === 'mainnet' ? bitcoin.networks.bitcoin : bitcoin.networks.testnet;
  const network = {
    ...base,
    bip32: {
      public: entry.public,
      private: entry.private,
    },
  };

  const node = bip32.fromBase58(xpub, network);
  // Enforce external chain (0) per BIP84; invoices use m/84'/coin_type'/0'/0/index.
  const child = node.derive(0).derive(index);
  const { address } = bitcoin.payments.p2wpkh({ pubkey: child.publicKey, network: base });
  if (!address) {
    throw new Error('Failed to derive address');
  }
  process.stdout.write(JSON.stringify({ address: address }) + '\n');
} catch (err) {
  console.error(err.message || 'Failed to derive address');
  process.exit(1);
}
