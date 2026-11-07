// Checks the actual minified APK, not a debug build or the ProGuard source rules.
// Usage: node docs/store/verify-android-release.mjs path/to/app-release.apk (or .aab)
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';

const apk = resolve(process.argv[2] || 'frontend/android/app/build/outputs/apk/release/app-release.apk');
const engine = resolve('frontend/node_modules/@capacitor/background-runner/android/src/main/libs/android-js-engine-release.aar');
const unzip = (...args) => execFileSync('unzip', args, { maxBuffer: 64 * 1024 * 1024 });
const temporary = mkdtempSync(join(tmpdir(), 'vb-release-check-'));
try {
  const jar = join(temporary, 'engine.jar');
  writeFileSync(jar, unzip('-p', engine, 'classes.jar'));
  const required = unzip('-Z1', jar).toString().split('\n')
    .filter((name) => name.startsWith('io/ionic/android_js_engine/') && name.endsWith('.class'))
    .map((name) => `L${name.slice(0, -6)};`);
  required.push('Lcom/getcapacitor/annotation/CapacitorPlugin;', 'Lcom/getcapacitor/annotation/Permission;', 'Lcom/getcapacitor/PluginMethod;');
  assert(required.length > 3, 'No JNI engine classes found in the source AAR');

  const classes = new Set();
  const dexFiles = unzip('-Z1', apk).toString().split('\n').filter((name) => /^(?:base\/dex\/)?classes\d*\.dex$/.test(name));
  assert(dexFiles.length, 'No DEX files found in APK');
  for (const filename of dexFiles) {
    const dex = unzip('-p', apk, filename);
    assert.equal(dex.subarray(0, 4).toString(), 'dex\n', 'Expected a standard Android DEX file');
    const stringsOffset = dex.readUInt32LE(0x3c);
    const typesOffset = dex.readUInt32LE(0x44);
    const count = dex.readUInt32LE(0x60);
    const definitionsOffset = dex.readUInt32LE(0x64);
    for (let index = 0; index < count; index++) {
      const classIndex = dex.readUInt32LE(definitionsOffset + index * 32);
      const stringIndex = dex.readUInt32LE(typesOffset + classIndex * 4);
      let start = dex.readUInt32LE(stringsOffset + stringIndex * 4);
      // Skip the ULEB128 UTF-16 length before the zero-terminated descriptor.
      while (dex[start++] & 0x80) {}
      classes.add(dex.subarray(start, dex.indexOf(0, start)).toString());
    }
  }
  const missing = required.filter((name) => !classes.has(name));
  assert.equal(missing.length, 0, `Release stripped or renamed JNI/permission classes:\n${missing.join('\n')}`);
  console.log(`PASS: ${required.length} JNI and permission classes retain their original names in ${dexFiles.length} release DEX files.`);
} finally {
  rmSync(temporary, { recursive: true, force: true });
}
