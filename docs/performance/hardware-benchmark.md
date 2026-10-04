# Hardware Performance Reference

<!-- START doctoc generated TOC please keep comment here to allow auto update -->
<!-- DON'T EDIT THIS SECTION, INSTEAD RE-RUN doctoc TO UPDATE -->

- [Purpose](#purpose)
- [Reference environment](#reference-environment)
  - [Saved-result metadata](#saved-result-metadata)
- [Installation](#installation)
- [Sysbench configuration and results](#sysbench-configuration-and-results)
- [FIO configuration and results](#fio-configuration-and-results)
  - [Throughput results](#throughput-results)
  - [Derived 4KB IOPS results](#derived-4kb-iops-results)
- [Reproduction procedure](#reproduction-procedure)
- [Normalized machine comparison](#normalized-machine-comparison)
- [Relationship to Kubernetes performance work](#relationship-to-kubernetes-performance-work)
- [Full and future quick storage profiles](#full-and-future-quick-storage-profiles)
- [Raw benchmark evidence](#raw-benchmark-evidence)

<!-- END doctoc generated TOC please keep comment here to allow auto update -->

## Purpose

This document defines the project's authoritative hardware performance reference for later Kubernetes and application performance experiments. It answers: **On what class of machine were the application performance results obtained?**

The current reference machine is `1.00x`. The raw Phoronix XML is versioned alongside this document so the displayed results and benchmark configuration can be audited.

## Reference environment

The following values come from the `System` entries in both saved `composite.xml` files. The CPU topology is recorded exactly as Phoronix reported it; it is not corrected from the processor's published specifications.

| Layer | Reference value |
|---|---|
| CPU | Intel Core i9-13900KF |
| CPU topology reported by Phoronix | 16 cores / 32 threads |
| Memory | 32 GB |
| Disks reported by Phoronix | Virtual Disk + 0GB Virtual Disk + 9GB Virtual Disk + 1100GB Virtual Disk |
| Operating system | Ubuntu 22.04 |
| Kernel | 6.18.40.1-microsoft-standard-WSL2 (x86_64) |
| System layer | `wsl` (WSL) |
| Filesystem | ext4 |
| Display server | Wayland |
| Phoronix Test Suite | 10.8.6 |

Phoronix also recorded the graphics adapter as an NVIDIA GeForce RTX 4090 24GB. It is not exercised by the Sysbench or FIO profiles documented here.

### Saved-result metadata

| Result | Phoronix identifier | Benchmark timestamp | Saved result last modified |
|---|---|---|---|
| Sysbench | `wsl i9 test` | 2026-10-04 17:13:12 | 2026-10-04 17:18:49 |
| FIO | `wsl` | 2026-10-04 17:31:18 | 2026-10-04 17:58:54 |

## Installation

Run the following on Ubuntu/WSL:

```bash
# Required dependencies
sudo apt update
sudo apt install -y \
    git \
    php-cli \
    php-xml \
    php-zip \
    php-curl \
    unzip \
    curl \
    sysbench \
    fio

# Prevent CRLF shell scripts under WSL/Linux
git config --global core.autocrlf input

# Install Phoronix Test Suite
cd /tmp
rm -rf phoronix-test-suite
git clone https://github.com/phoronix-test-suite/phoronix-test-suite.git
cd phoronix-test-suite
sudo ./install-sh

# Verify installation
php -v
sysbench --version
fio --version
phoronix-test-suite version
phoronix-test-suite system-info

# Install benchmark profiles
phoronix-test-suite install system/sysbench
phoronix-test-suite install system/fio
```

If `./install-sh` fails with a misleading `No such file or directory`, check that the repository was not cloned with CRLF line endings. Run `git config --global core.autocrlf input`, remove the failed clone, and clone it again.

## Sysbench configuration and results

Run the benchmark from the Linux filesystem:

```bash
cd ~
phoronix-test-suite benchmark system/sysbench
```

At `System Test Configuration`, select `3: Test All Options`. This runs both `CPU` and `RAM / Memory`.

The saved result uses Phoronix profile `system/sysbench-1.0.0`. Its result metadata reports the underlying application as `sysbench 1.0.20 (using system LuaJIT 2.1.0-beta3)`. Both metrics are Higher Is Better (HIB).

| Test | Exact arguments | Average | Unit | Raw individual runs | Deviation | Proportion |
|---|---|---:|---|---|---|---|
| RAM / Memory | `memory run` | 14038.41 | MiB/sec | 14241.06, 13848.43, 14025.73 | Not stored in XML | HIB |
| CPU | `cpu run` | 78338.14 | Events Per Second | 78234.51, 78323.57, 78456.35 | Not stored in XML | HIB |

For these records, HIB means a larger throughput value is better. Phoronix stored the individual run times as `10.45, 7.42, 7.33` seconds for memory and `90.03, 90.04, 90.03` seconds for CPU.

## FIO configuration and results

Run the storage benchmark from the Linux/WSL filesystem, such as the home directory. Do **not** run the reference benchmark from `/mnt/c/...`; that would benchmark the Windows-mounted filesystem instead of the reference ext4 environment.

```bash
cd ~
phoronix-test-suite benchmark system/fio
```

Use these exact interactive selections:

| Prompt | Selection | Effective configuration |
|---|---|---|
| Disk Test Configuration | `1,2,3,4` | Random Read, Random Write, Sequential Read, Sequential Write |
| IO Engine | `3` | Linux AIO |
| Buffered | `2` | No |
| Direct | `2` | Yes |
| Block Size | `1,9` | 4KB and 1MB |

The resulting full matrix has eight configurations: every combination of the four operations and the two block sizes. All use Linux AIO (`libaio`), Buffered `No` (`0`), Direct `Yes` (`1`), and the Phoronix `Default Test Directory`.

The saved result uses profile `system/fio-1.9.5` and underlying FIO version `3.28`. Every recorded metric is HIB. The profile emits MB/s for all eight configurations and also emits IOPS as a derived result for each 4KB configuration.

### Throughput results

| Operation | Block size | Engine | Buffered | Direct | Exact arguments | Result | Unit | Raw individual runs | Deviation | Proportion |
|---|---:|---|---|---|---|---:|---|---|---|---|
| Random Read | 4KB | Linux AIO | No | Yes | `randread libaio 0 1 4k` | 194 | MB/s | 188, 184, 201, 188, 195, 193, 210, 190, 186, 212, 188, 198, 200, 190, 182 | Not stored in XML | HIB |
| Random Read | 1MB | Linux AIO | No | Yes | `randread libaio 0 1 1m` | 527 | MB/s | 526, 530, 525 | Not stored in XML | HIB |
| Random Write | 4KB | Linux AIO | No | Yes | `randwrite libaio 0 1 4k` | 167 | MB/s | 163, 167, 151, 214, 161, 158, 175, 171, 156, 158, 166, 163 | Not stored in XML | HIB |
| Random Write | 1MB | Linux AIO | No | Yes | `randwrite libaio 0 1 1m` | 355 | MB/s | 352, 351, 361 | Not stored in XML | HIB |
| Sequential Read | 4KB | Linux AIO | No | Yes | `read libaio 0 1 4k` | 180 | MB/s | 179, 180, 180 | Not stored in XML | HIB |
| Sequential Read | 1MB | Linux AIO | No | Yes | `read libaio 0 1 1m` | 367 | MB/s | 366, 371, 365 | Not stored in XML | HIB |
| Sequential Write | 4KB | Linux AIO | No | Yes | `write libaio 0 1 4k` | 160 | MB/s | 159, 161, 160 | Not stored in XML | HIB |
| Sequential Write | 1MB | Linux AIO | No | Yes | `write libaio 0 1 1m` | 482 | MB/s | 479, 480, 486 | Not stored in XML | HIB |

### Derived 4KB IOPS results

| Operation | Block size | Engine | Buffered | Direct | Exact arguments | Result | Unit | Raw individual runs | Deviation | Proportion |
|---|---:|---|---|---|---|---:|---|---|---|---|
| Random Read | 4KB | Linux AIO | No | Yes | `randread libaio 0 1 4k` | 49573 | IOPS | 48200, 47000, 51600, 48100, 50000, 49300, 53900, 48700, 47700, 54200, 48000, 50700, 51100, 48500, 46600 | Not stored in XML | HIB |
| Random Write | 4KB | Linux AIO | No | Yes | `randwrite libaio 0 1 4k` | 42717 | IOPS | 41700, 42800, 38600, 54800, 41200, 40400, 44700, 43700, 40000, 40400, 42600, 41700 | Not stored in XML | HIB |
| Sequential Read | 4KB | Linux AIO | No | Yes | `read libaio 0 1 4k` | 46000 | IOPS | 45900, 46000, 46100 | Not stored in XML | HIB |
| Sequential Write | 4KB | Linux AIO | No | Yes | `write libaio 0 1 4k` | 41033 | IOPS | 40800, 41300, 41000 | Not stored in XML | HIB |

The complete per-run timing arrays remain available in the raw FIO XML.

## Reproduction procedure

After installation, run:

```bash
# Work from Linux/WSL filesystem
cd ~

# Record machine/environment
phoronix-test-suite system-info

# CPU + RAM
phoronix-test-suite benchmark system/sysbench

# Storage
phoronix-test-suite benchmark system/fio

# Find saved results
ls -la ~/.phoronix-test-suite/test-results/
```

For Sysbench, select `3` at `System Test Configuration`. For FIO, select `1,2,3,4` for the disk tests, `3` for Linux AIO, `2` for Buffered = No, `2` for Direct = Yes, and `1,9` for 4KB plus 1MB block sizes.

Each saved run's machine-readable result is located at:

```text
~/.phoronix-test-suite/test-results/<result-id>/composite.xml
```

When publishing a replacement baseline, copy only the relevant original `composite.xml` files into the repository, without modifying them. Do not copy caches, installed tests, downloaded archives, temporary files, or unrelated results.

## Normalized machine comparison

Treat every metric on this reference machine as `1.00x`. For HIB metrics:

```text
relative_performance = tested_machine_result / reference_machine_result
```

For example, a tested CPU result of `60000` is `60000 / 78338.14 ~= 0.77x` relative to this reference CPU result.

Compare dimensions independently: CPU, memory, random storage I/O, sequential storage I/O, and read versus write. Keep block sizes and units identical. Do not combine them into an arbitrary overall hardware score.

## Relationship to Kubernetes performance work

The intended workflow is:

```text
hardware benchmark
    -> fixed Kubernetes replicas
    -> k6 load test
    -> CPU / RAM / PHP-FPM / latency / queue analysis
    -> requests/limits calibration
    -> HPA
    -> repeat k6
    -> validate scaling
```

The hardware benchmark records the class of machine behind application results. It does not directly determine Kubernetes requests or limits, and requests/limits must not be mechanically scaled from Sysbench or FIO ratios. Fixed-replica application-level k6 testing remains the authoritative calibration step before HPA experiments.

## Full and future quick storage profiles

The authoritative initial reference is the full eight-configuration FIO matrix documented above. It is intentionally comprehensive and may take significant time.

A future quick comparison profile may explicitly adopt only Random Read / 4KB, Random Write / 4KB, Sequential Read / 1MB, and Sequential Write / 1MB. Until the project formally adopts such a profile, it does not replace the full initial baseline.

## Raw benchmark evidence

- [Original Sysbench composite.xml](benchmark-results/sysbench.xml), copied from saved result ID `test-resulttxt`
- [Original FIO composite.xml](benchmark-results/fio.xml), copied from saved result ID `ssd-io-results`
