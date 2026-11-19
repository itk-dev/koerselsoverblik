import { Controller } from "@hotwired/stimulus";

/*
 * The bulk upload. The Dropzone field takes the files. This controller sends each file in its own
 * request, one request at a time, so that the request limit applies to each file. The server
 * renders the row of each file, and the controller puts it in the list.
 */
export default class extends Controller {
  static targets = [
    "list",
    "count",
    "importButton",
    "waitingRow",
    "failedRow",
    "doneDialog",
  ];

  static values = { checkUrl: String, csrfToken: String };

  connect() {
    // The requests run one after the other.
    this.queue = Promise.resolve();
    this.running = 0;
    // Dropzone keeps all the files of earlier drops in its change event. Send each file once.
    this.seen = new Set();
  }

  /*
   * Adds the new files of a dropzone:change event, and checks them.
   */
  add(event) {
    const files =
      event.detail instanceof FileList
        ? Array.from(event.detail)
        : [event.detail];
    for (const file of files) {
      if (!file) {
        continue;
      }
      const key = [file.name, file.size, file.lastModified].join("|");
      if (this.seen.has(key)) {
        continue;
      }
      this.seen.add(key);

      const body = new FormData();
      body.append("file", file);
      const row = this.copyRow(this.waitingRowTarget, file.name);
      this.listTarget.append(row);
      this.enqueue(() => this.send(row, this.checkUrlValue, body));
    }
    this.update();
  }

  /*
   * Imports the checked files, in the order of the list.
   */
  import() {
    for (const row of this.listTarget.querySelectorAll(
      '[data-state="checked"]',
    )) {
      // A second click must not import the file again.
      row.dataset.state = "waiting";
      this.enqueue(() => this.send(row, row.dataset.importUrl, null));
    }
    // The last task of the queue tells the user that the import is done. update() has set the
    // counts of the dialog after the last file.
    this.enqueue(() => this.doneDialogTarget.showModal());
    this.update();
  }

  enqueue(task) {
    this.running += 1;
    this.queue = this.queue.then(task).finally(() => {
      this.running -= 1;
      this.update();
    });
  }

  /*
   * Sends one request, and puts the row of the response in place of the row. A response with
   * no row, for example after a lost connection, gives a failed row.
   */
  async send(row, url, body) {
    let next = null;
    try {
      const response = await fetch(url, {
        method: "POST",
        body,
        headers: { "X-CSRF-Token": this.csrfTokenValue },
      });
      if (response.ok || response.status === 422) {
        const template = document.createElement("template");
        template.innerHTML = await response.text();
        next = template.content.querySelector('[data-test="bulk-file"]');
      }
    } catch {
      // The failed row below tells the user.
    }

    const name = row.querySelector('[data-test="bulk-name"]').textContent;
    next ??= this.copyRow(this.failedRowTarget, name);
    // The server does not know the name of an expired upload. Keep the name of the row.
    next.querySelector('[data-test="bulk-name"]').textContent = name;
    row.replaceWith(next);
    // During the import, the list follows the file that is done. The check does not scroll, so
    // that the user can read the list while the files are checked.
    if (body === null) {
      next.scrollIntoView({ block: "nearest" });
    }
  }

  copyRow(template, name) {
    const row = template.content.firstElementChild.cloneNode(true);
    row.querySelector('[data-test="bulk-name"]').textContent = name;

    return row;
  }

  /*
   * Shows the number of files in each state. The import button is active when no request runs
   * and a file is checked.
   */
  update() {
    for (const count of this.countTargets) {
      count.textContent = String(
        this.listTarget.querySelectorAll(
          `[data-state="${count.dataset.state}"]`,
        ).length,
      );
    }
    this.importButtonTarget.disabled =
      this.running > 0 ||
      this.listTarget.querySelector('[data-state="checked"]') === null;
  }
}
