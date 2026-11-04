import "./styles/app.css";

/*
 * The behaviour of the page shell: the navigation drawer on small screens and the
 * "Om tallene" dialog.
 */

const shell = document.querySelector("[data-shell]");
const rail = document.querySelector("[data-rail]");
const toggle = document.querySelector("[data-nav-toggle]");
const nav = document.querySelector("[data-nav]");
// The drawer is only on small screens. Keep this width the same as in app.css.
const wideScreen = window.matchMedia("(min-width: 1024px)");

function isNavOpen() {
    return shell.classList.contains("is-nav-open");
}

function setNavOpen(open) {
    shell.classList.toggle("is-nav-open", open);
    toggle.setAttribute("aria-expanded", String(open));
}

if (shell && rail && toggle && nav) {
    toggle.addEventListener("click", () => {
        setNavOpen(!isNavOpen());
    });

    // On small screens, a click on a link closes the drawer.
    nav.addEventListener("click", (event) => {
        if (event.target.closest("a")) {
            setNavOpen(false);
        }
    });

    // A click outside the drawer, for example on the backdrop, closes it.
    document.addEventListener("click", (event) => {
        if (
            isNavOpen() &&
            !rail.contains(event.target) &&
            !toggle.contains(event.target)
        ) {
            setNavOpen(false);
        }
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && isNavOpen()) {
            setNavOpen(false);
            toggle.focus();
        }
    });

    // On a wide screen, the rail is always visible, so the drawer must not stay open.
    wideScreen.addEventListener("change", (event) => {
        if (event.matches) {
            setNavOpen(false);
        }
    });
}

const aboutOpen = document.querySelector("[data-about-open]");
const aboutDialog = document.querySelector("[data-about-dialog]");

if (aboutOpen && aboutDialog) {
    aboutOpen.addEventListener("click", () => {
        aboutDialog.showModal();
    });

    // A click on the backdrop closes the dialog. The form fills the dialog box, so only a click
    // outside the box has the dialog itself as its target. The press must also start there, so
    // that a text selection that ends outside the box does not close the dialog.
    let pressOnBackdrop = false;
    aboutDialog.addEventListener("pointerdown", (event) => {
        pressOnBackdrop = event.target === aboutDialog;
    });
    aboutDialog.addEventListener("click", (event) => {
        if (pressOnBackdrop && event.target === aboutDialog) {
            aboutDialog.close();
        }
    });
}
