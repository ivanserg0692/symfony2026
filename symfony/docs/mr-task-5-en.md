# MR Task 5 Result Log

## Overview

This document is a placeholder for the visible result of merge request 5.

Merge request: <https://github.com/ivanserg0692/symfony2026/pull/5>

The task covers asynchronous news export to S3-compatible storage through Symfony Messenger and RabbitMQ, with MinIO used for local development.

## Planned Result

The expected result includes:
- MinIO integration in the local Docker Compose environment
- S3-compatible storage configuration through environment variables
- a news export startup flow
- asynchronous export processing through Symfony Messenger and RabbitMQ
- generated export files stored in MinIO/S3
- export status tracking for successful and failed jobs

## Screenshots

Screenshots will be added after the implementation is available for visual verification.

## Updates for 2026-05-02

- task 5 documentation was prepared
- merge request 5 placeholder log was created
- README links were prepared for the task file, merge request, and result logs

## Updates for 2026-05-05

### News Export Messenger Batch Architecture

The implementation adds an asynchronous export pipeline on top of Symfony Messenger, RabbitMQ, Doctrine, and S3-compatible storage. The core idea is that one news item remains one queue message, but the worker handles messages in batches through `BatchHandlerInterface` to reduce database queries and external storage writes.

Overall pipeline:

- the administrator starts export for selected news or all news
- the application creates a generic `MessengerBatch` record
- the application creates a domain-level `NewsExport` record linked to the batch
- one `ExportNewsMessage` is dispatched to RabbitMQ per news item
- the Symfony Messenger worker groups messages into handler batches
- the handler loads news with one query and writes one CSV chunk per handler batch
- after all messages are complete, a finalize message is dispatched
- the finalizer merges CSV chunks into one final CSV file and deletes temporary chunks
- the final file path is saved in `NewsExport`

<!-- plantuml src="plantuml/news-export/pipeline.puml" alt="News export batch pipeline" out="images/plantuml/news-export/pipeline.png" -->
![News export batch pipeline](images/plantuml/news-export/pipeline.png)
<!-- /plantuml -->

### Export Startup

Export startup is handled by `NewsExportStarter`. This service does not generate the file and does not load full news records. Its responsibility is limited to preparing the execution:

- get news ids from the administrator selection or load all ids
- normalize ids and keep `news.id ASC` order
- create `MessengerBatch` with the total message count
- create `NewsExport` as the domain export journal record
- move the batch into the started state
- dispatch one `ExportNewsMessage` to RabbitMQ per news item

This keeps RabbitMQ responsible for storing the queued work, while `MessengerBatch` stores only status and counters.

<!-- plantuml src="plantuml/news-export/startup.puml" alt="Export startup responsibilities" out="images/plantuml/news-export/startup.png" -->
![Export startup responsibilities](images/plantuml/news-export/startup.png)
<!-- /plantuml -->

### Message Batch Processing

`ExportNewsMessageHandler` implements `BatchHandlerInterface`. Therefore each message still represents one news item, but the worker processes multiple messages in one `process()` call.

Inside one handler batch:

- news ids are collected from all messages
- news records are loaded with one repository query
- missing news messages are collected separately
- found news items are sorted by id
- one CSV chunk is written for the whole handler batch
- successful messages are acknowledged with `ack`
- missing news messages are negatively acknowledged with `UnrecoverableMessageHandlingException`

This reduces news selection load: instead of one select per message, the handler performs one select per batch of 50 messages.

<!-- plantuml src="plantuml/news-export/message-batch-processing.puml" alt="Messenger handler batch processing" out="images/plantuml/news-export/message-batch-processing.png" -->
![Messenger handler batch processing](images/plantuml/news-export/message-batch-processing.png)
<!-- /plantuml -->

### Retry and Errors

Retry behavior depends on the exception type passed to `ack->nack()`.

For temporary errors, for example MinIO/S3 being unavailable, the handler passes the original exception. This is recoverable, so Symfony Messenger can apply the transport retry policy.

For permanent errors, for example a news record no longer existing, the handler uses `UnrecoverableMessageHandlingException`. That message should not be retried because reprocessing will not restore the missing database row.

<!-- plantuml src="plantuml/news-export/retry.puml" alt="Retry behavior" out="images/plantuml/news-export/retry.png" -->
![Retry behavior](images/plantuml/news-export/retry.png)
<!-- /plantuml -->

### CSV Chunks and Merge Order

`NewsExportCsvStorage` owns the file storage part of the export. The handler passes already sorted news entities to storage, and storage:

- extracts the id of the first news item in the chunk
- uses this id in the chunk filename
- writes a temporary CSV chunk to S3-compatible storage
- finds all chunks during finalization
- sorts chunk paths lexicographically
- merges chunks into one CSV with a header
- deletes temporary chunks

The chunk filename is based on the first `news.id` in the batch with padding. This keeps final merge order stable because chunk order follows news order.

<!-- plantuml src="plantuml/news-export/chunk-merge.puml" alt="CSV chunk ordering and final merge" out="images/plantuml/news-export/chunk-merge.png" -->
![CSV chunk ordering and final merge](images/plantuml/news-export/chunk-merge.png)
<!-- /plantuml -->

### Batch Finalization

Finalization is moved into a separate message and handler. This keeps final file assembly out of regular news message processing and starts it only after the batch has completed.

`FinalizeNewsExportMessageHandler` checks the linked `MessengerBatch` status:

- if the batch failed, temporary chunks are deleted and no final CSV is created
- if the batch is not finished yet, finalization is skipped
- if the batch finished successfully, chunks are merged into one final CSV
- the final file path is saved into `NewsExport.filePath`

<!-- plantuml src="plantuml/news-export/finalization.puml" alt="Finalize news export" out="images/plantuml/news-export/finalization.png" -->
![Finalize news export](images/plantuml/news-export/finalization.png)
<!-- /plantuml -->

### Admin Visibility

`NewsExport` is used as the domain export journal. It is linked to the generic `MessengerBatch`, so the admin panel can show export state without reading RabbitMQ.

In the admin panel:

- the news list can start export for selected records
- if nothing is selected, export starts for all news
- the export list shows batch state and the final file path
- export CRUD is read-only because the record reflects background process state

<!-- plantuml src="plantuml/news-export/admin-visibility.puml" alt="Admin visibility" out="images/plantuml/news-export/admin-visibility.png" -->
![Admin visibility](images/plantuml/news-export/admin-visibility.png)
<!-- /plantuml -->

### Responsibility Split

The architecture is split into several components because different pipeline parts have different error sources and side effects:

- `NewsExportStarter` creates the batch and fills RabbitMQ with messages
- `ExportNewsMessage` represents one news item export task
- `ExportNewsMessageHandler` groups messages, loads news, and writes CSV chunks
- `FinalizeNewsExportMessage` triggers final assembly
- `FinalizeNewsExportMessageHandler` completes the export or cleans chunks for failed batches
- `NewsExportCsvStorage` owns CSV generation, chunk paths, final paths, and S3-compatible storage access
- `MessengerBatch` stores generic batch state
- `NewsExport` links the generic batch to the news export domain

<!-- plantuml src="plantuml/news-export/components.puml" alt="News export components" out="images/plantuml/news-export/components.png" -->
![News export components](images/plantuml/news-export/components.png)
<!-- /plantuml -->
