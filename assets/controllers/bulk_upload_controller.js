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
    "confirmDialog",
    "doneDialog",
  ];

  static values = { checkUrl: String, csrfToken: String, busyText: String };

  connect() {
    // The requests run one after the other.
    this.queue = Promise.resolve();
    this.running = 0;
    // Dropzone keeps all the files of earlier drops in its change event. Send each file once.
    this.seen = new Set();
    // A file that is dropped outside the active drop area must not open in the tab. That leaves
    // the page and stops the import. Firefox opens such a file.
    this.blockDrop = (event) => {
      const input = this.element.querySelector(".dropzone-input");
      if (event.target !== input || input.disabled) {
        event.preventDefault();
      }
    };
    window.addEventListener("dragover", this.blockDrop);
    window.addEventListener("drop", this.blockDrop);
  }

  disconnect() {
    window.removeEventListener("dragover", this.blockDrop);
    window.removeEventListener("drop", this.blockDrop);
  }

  /*
   * Adds the new files of a dropzone:change event, and checks them.
   */
  add(event) {
    const files =
      event.detail instanceof FileList
        ? Array.from(event.detail)
        : [event.detail];
    // The first files after an import start a new list.
    if (this.clearListOnAdd && files.some(Boolean)) {
      this.listTarget.replaceChildren();
      this.clearListOnAdd = false;
    }
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
   * Asks the user to keep the page open. The page sends the files, so the import stops if the
   * user leaves the page.
   */
  confirm() {
    this.confirmDialogTarget.returnValue = "";
    this.confirmDialogTarget.showModal();
  }

  /*
   * Starts the import when the user closes the dialog with the import button.
   */
  confirmed() {
    if (this.confirmDialogTarget.returnValue === "import") {
      this.import();
    }
  }

  /*
   * Imports the checked files, in the order of the list.
   */
  import() {
    const rows = this.listTarget.querySelectorAll('[data-state="checked"]');
    if (rows.length === 0) {
      return;
    }
    // No new files during the import. reset() makes the drop area active again.
    this.setDropzoneActive(false);
    for (const row of rows) {
      // A second click must not import the file again.
      row.dataset.state = "waiting";
      this.enqueue(() => this.send(row, row.dataset.importUrl, null));
    }
    // The last task of the queue tells the user that the import is done. update() has set the
    // counts of the dialog after the last file.
    this.enqueue(() => this.doneDialogTarget.showModal());
    this.update();
  }

  /*
   * Makes the drop area empty and active again when the user closes the dialog of the import.
   * The same files can then be dropped again. Dropzone shows no clear button in its multiple mode,
   * but its controller has clear().
   */
  reset() {
    const container = this.element.querySelector(".dropzone-container");
    this.application
      .getControllerForElementAndIdentifier(
        container,
        "symfony--ux-dropzone--dropzone",
      )
      ?.clear();
    this.seen.clear();
    this.setDropzoneActive(true);
    // The rows of the import stay until the next drop, so that the user can read them.
    this.clearListOnAdd = true;
  }

  setDropzoneActive(active) {
    const container = this.element.querySelector(".dropzone-container");
    container.classList.toggle("is-busy", !active);
    container.dataset.busyText = this.busyTextValue;
    container.querySelector(".dropzone-input").disabled = !active;
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
