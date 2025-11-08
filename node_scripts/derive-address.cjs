#!/usr/bin/env node
const tinysecp = require('tiny-secp256k1');
const bip32Factory = require('bip32');
const bitcoin = require('bitcoinjs-lib');

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

const networks = {
  testnet: bitcoin.networks.testnet,
  mainnet: bitcoin.networks.bitcoin,
};

const network = networks[networkArg] || networks.testnet;

try {
  const bip32 = bip32Factory.BIP32Factory(tinysecp);
  const node = bip32.fromBase58(xpub, network);
  const child = node.derive(index);
  const { address } = bitcoin.payments.p2wpkh({ pubkey: child.publicKey, network });
  if (!address) {
    throw new Error('Failed to derive address');
  }
  process.stdout.write(JSON.stringify({ address: address }) + '\n');
} catch (err) {
  console.error(err.message || 'Failed to derive address');
  process.exit(1);
}
