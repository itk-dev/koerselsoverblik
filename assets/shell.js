/*
 * The behaviour of the page shell: the navigation drawer on small screens and the
 * "Om tallene" dialog. The template has no inline scripts, because the Content Security
 * Policy blocks them.
 */

const shell = document.querySelector("[data-shell]");
const toggle = document.querySelector("[data-nav-toggle]");
const nav = document.querySelector("[data-nav]");

function setNavOpen(open) {
    shell.classList.toggle("is-nav-open", open);
    toggle.setAttribute("aria-expanded", String(open));
}

if (shell && toggle && nav) {
    toggle.addEventListener("click", () => {
        setNavOpen(!shell.classList.contains("is-nav-open"));
    });

    // On small screens, a click on a link closes the drawer.
    nav.addEventListener("click", (event) => {
        if (event.target.closest("a")) {
            setNavOpen(false);
        }
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && shell.classList.contains("is-nav-open")) {
            setNavOpen(false);
            toggle.focus();
        }
    });
}

const aboutOpen = document.querySelector("[data-about-open]");
const aboutDialog = document.querySelector("[data-about-dialog]");

if (aboutOpen && aboutDialog) {
    aboutOpen.addEventListener("click", () => {
        aboutDialog.showModal();
    });
}
