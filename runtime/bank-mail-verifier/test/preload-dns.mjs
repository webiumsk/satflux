// Deliberately outside production entry points: offline integration launcher only.
// Its file contains public synthetic DNS records, never signing keys or real mail.
import { Resolver } from 'node:dns/promises';
import { readFileSync } from 'node:fs';
const fixture = JSON.parse(readFileSync(process.env.SATFLUX_TEST_DNS_FIXTURE, 'utf8'));
Resolver.prototype.resolveTxt = async function resolveSyntheticTxt(name) {
  if (name !== fixture.dnsName || typeof fixture.dnsRecord !== 'string') {
    throw Object.assign(new Error('No synthetic DNS record'), { code: 'ENOTFOUND' });
  }
  return [[fixture.dnsRecord]];
};
