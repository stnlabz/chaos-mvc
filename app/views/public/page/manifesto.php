<?php
require_once APPROOT . '/views/inc/head.php';
require_once APPROOT . '/lib/render_md.php';
$render = new render_md();
$text ="
# The Chaos MVC Manifesto
**Architecture Without the Shadows**

## Stop Fighting the Framework.
Look, modern development is a goddamn mess. It’s a race to see who can bury more logic under layers of abstractions and bloated **garbage**. We are done with the shady, black-box mystery meat. **Chaos MVC** is for the developers who actually want to own their stack. If you want to rent your brain out to a framework, **go somewhere else**.

## We Don't Hide the Wires.
Transparency isn’t some buzzword here; it’s the law. From the **Example module** to the **Developer Portal**, we are exposing the skeleton of this system. We prioritize a predictable execution path—Request to Response—where every single step is traceable. You want to know how it works? Look at the code. We have made sure it’s clean enough to read.

## Discipline is Your Freedom.
We enforce rules so the framework doesn’t rot while you’re sleeping.
 - **Lowercase or Leave**: Files, classes, controllers. Lowercase. No exceptions. We value filesystem sanity over your creative capitalization.
 - **The Annotation Protocol**: If an AI touches the code, you mark it. If you modify the core, you sign it. We value the **Soldier’s Seal** over your convenience. 
 - **Zero-Bloat**: If it’s not essential, it stays out of the Core. We ship lean, mean, and working.

## No Magic. No Nonsense. 
We don't do telemetry. We don't do hidden tracking. We don't do auto magic that hides your database health. We would rather manually harden a schema—fixing indexes and `AUTO_INCREMENT` ourselves, than trust a system that breaks the second you look at it sideways. 

## Competency is the Watchword.
This is for the solid developers. The ones who respect the craft enough to keep it precise. We have provided the architecture; you provide the skill. We are building an ecosystem where the code is clear, the flow is predictable, and the developer is actually the one in charge. 

**Discipline over convenience. Every. Single. Time.**
";
echo $render->markdown($text);
require_once APPROOT . '/views/inc/foot.php';
