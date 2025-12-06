console.log("Accordion loaded!")
function prepAccordions() {
    const accordions = document.querySelectorAll("ul.accordion");
    accordions.forEach((accordion, accordionNumber) => {
        const items = [...accordion.querySelectorAll(":scope > li")].map(d => { return { node: d, title: d.firstElementChild.textContent, tag: d.firstElementChild.tagName, content: nodeListToString(d.querySelectorAll(":scope > *:first-child ~ *")) } });
        items.forEach((item, itemNumber) => restructure(item, accordionNumber, itemNumber));
    });
    function restructure(item,accordionNumber, itemNumber) {
        const id = "accordion-" + accordionNumber + "-" + itemNumber;
        item.node.innerHTML = `<${item.tag}><button aria-expanded='false' aria-controls='${id}'>${item.title}</button></${item.tag}><div aria-hidden='true' id='${id}'><div>${item.content}</div></div>`
    }
    function nodeListToString(list) {
        return [...list].map(d => d.outerHTML).join(" ");
    }
}
function setAccordionInteraction() {
    const accordions = document.querySelectorAll("ul.accordion");
    accordions.forEach(parent => {
        const
            accordions = [...parent.querySelectorAll(":scope > li")].map(d => { return { node: d, button: d.querySelector("button"), content: d.querySelector("div[aria-hidden]") } }),
            openAccordion = accordion => {
                accordion.node.style.setProperty("--height", accordion.content.firstElementChild.offsetHeight + "px");
                accordion.button.ariaExpanded = true;
                accordion.content.ariaHidden = false;
            },
            closeAccordion = accordion => {
                accordion.node.style.setProperty("--height", accordion.content.firstElementChild.offsetHeight + "px");
                requestAnimationFrame(() => requestAnimationFrame(() => {
                    accordion.node.style.setProperty("--height", "0");
                    accordion.button.ariaExpanded = false;
                    accordion.content.ariaHidden = true;
                }));
            },
            click = accordion => {
                accordions.filter(d => d.button.ariaExpanded == "true").forEach(otherAccordion => closeAccordion(otherAccordion))
                accordion.button.ariaExpanded == "false" ? openAccordion(accordion) : closeAccordion(accordion)
            }
        accordions.forEach(accordion => {
            accordion.button.addEventListener("click", () => click(accordion))
            accordion.content.addEventListener("transitionend", () => accordion.node.style.removeProperty("--height"));
        });
    });
}
function setupAccordions() {
    prepAccordions();
    setAccordionInteraction();
}
if (document.querySelector("ul.accordion")) setupAccordions();
