<script setup>
import { RouterLink } from 'vue-router';
</script>

<template>
    <article class="space-y-6">
        <header>
            <h1 class="text-2xl font-semibold text-ink">Términos y condiciones</h1>
            <p class="mt-1 text-sm text-muted">Última actualización: 24 de agosto de 2026</p>
        </header>

        <section class="space-y-2">
            <h2 class="text-base font-semibold text-ink">Aceptación</h2>
            <p class="text-sm text-muted">
                Al acceder a Marketing AI Manager aceptas estos términos y la
                <RouterLink to="/privacy" class="text-brand-600 hover:text-brand-700">política de privacidad</RouterLink>.
                Si no estás de acuerdo, no uses la aplicación.
            </p>
        </section>

        <section class="space-y-2">
            <h2 class="text-base font-semibold text-ink">El servicio</h2>
            <p class="text-sm text-muted">
                La aplicación te ayuda a planificar y ejecutar trabajo de marketing con apoyo de
                inteligencia artificial. Las acciones que afectan a plataformas externas se presentan
                siempre como propuestas: nada se publica ni se modifica fuera de la aplicación sin tu
                aprobación explícita.
            </p>
        </section>

        <section class="space-y-2">
            <h2 class="text-base font-semibold text-ink">Tu cuenta</h2>
            <p class="text-sm text-muted">
                El acceso es con Google. Eres responsable de la actividad realizada desde tu cuenta y
                de mantener seguro el acceso a tu correo. Debes tener capacidad legal para contratar y
                autorización para conectar las cuentas de marketing que vincules.
            </p>
        </section>

        <section class="space-y-2">
            <h2 class="text-base font-semibold text-ink">Uso aceptable</h2>
            <ul class="list-disc space-y-1 pl-5 text-sm text-muted">
                <li>No usar el servicio para contenido ilegal, engañoso o que infrinja derechos de terceros.</li>
                <li>No intentar acceder a datos de otras cuentas ni a partes no autorizadas del sistema.</li>
                <li>No revender el servicio ni automatizar su uso de forma que degrade la plataforma.</li>
                <li>Respetar los términos de las plataformas externas que conectes.</li>
            </ul>
        </section>

        <section class="space-y-2">
            <h2 class="text-base font-semibold text-ink">Contenido y propiedad</h2>
            <p class="text-sm text-muted">
                Los datos y el contenido que introduces siguen siendo tuyos. Nos concedes solo el
                permiso necesario para procesarlos y prestarte el servicio. El software, la marca y la
                interfaz de la aplicación son nuestros.
            </p>
        </section>

        <section class="space-y-2">
            <h2 class="text-base font-semibold text-ink">Resultados de la IA</h2>
            <p class="text-sm text-muted">
                Las respuestas y propuestas generadas por la inteligencia artificial pueden contener
                errores. Revísalas antes de aprobarlas o publicarlas: la decisión final y su
                responsabilidad son tuyas. No constituyen asesoramiento legal, fiscal ni profesional.
            </p>
        </section>

        <section class="space-y-2">
            <h2 class="text-base font-semibold text-ink">Disponibilidad y límites</h2>
            <p class="text-sm text-muted">
                El servicio se ofrece «tal cual». Podemos cambiarlo, suspenderlo o aplicar límites de
                uso, y no garantizamos disponibilidad ininterrumpida. En la medida que la ley lo
                permita, no respondemos por daños indirectos ni por lucro cesante.
            </p>
        </section>

        <section class="space-y-2">
            <h2 class="text-base font-semibold text-ink">Terminación</h2>
            <p class="text-sm text-muted">
                Puedes dejar de usar el servicio cuando quieras. Podemos suspender el acceso ante un
                incumplimiento de estos términos.
            </p>
        </section>

        <section class="space-y-2">
            <h2 class="text-base font-semibold text-ink">Cambios</h2>
            <p class="text-sm text-muted">
                Si actualizamos estos términos, publicaremos aquí la nueva versión con su fecha. Seguir
                usando la aplicación implica aceptarla.
            </p>
        </section>
    </article>
</template>
