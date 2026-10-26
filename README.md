# LEDCalle

Aplicación para contratar publicidad en pantallas LED: 45 bloques de 20 segundos que forman un ciclo de 15 minutos que se repite de 06:00 a 23:00.

Tiene dos partes: una **web para clientes** (móvil primero) y un **backoffice para propietarios** de pantallas.

## Contenido del repositorio

| Carpeta | Qué es | Dónde se despliega |
|---|---|---|
| `public/` | **Demo** en HTML puro. Datos de ejemplo en memoria, sin base de datos. | Vercel |
| `plugin/ledcalle/` | **Plugin de WordPress** con base de datos real, subida de archivos, emails y login de propietarios. | Hosting con WordPress |

> Vercel solo sirve la demo. El plugin necesita PHP y MySQL, que Vercel no ofrece.

## Ver la demo en local

```bash
npm start
```

O simplemente abre `public/index.html` en el navegador.

## Desplegar la demo en Vercel

1. Sube este repositorio a GitHub.
2. En [vercel.com](https://vercel.com): **Add New > Project** e importa el repositorio.
3. Framework Preset: **Other**. No toques nada más (`vercel.json` ya indica la carpeta `public`).
4. Pulsa **Deploy**.

Cada `git push` a la rama principal vuelve a desplegar automáticamente.

## Instalar el plugin en WordPress

```bash
npm run plugin:zip      # o: sh scripts/build-plugin.sh
```

Sube el `ledcalle.zip` en **Plugins > Añadir nuevo > Subir plugin** y actívalo. Después:

- Página para clientes con el shortcode `[ledcalle]`
- Página para propietarios con el shortcode `[ledcalle_admin]` (no la pongas en la misma que la anterior)

Más detalles (pagos, fuentes, RGPD) en [`plugin/ledcalle/README.txt`](plugin/ledcalle/README.txt).

Para publicar una versión descargable: crea una etiqueta (`git tag v1.0.1 && git push --tags`) y GitHub Actions adjuntará `ledcalle.zip` a una Release.

## Pendiente

- Pasarela de pago (el plugin ofrece el filtro `ledcalle_checkout_url`).
- Creador de anuncios con IA real (ahora usa plantillas).
- Elegir una licencia para el repositorio.
- La demo carga las fuentes desde Google Fonts. Por RGPD conviene autoalojarlas antes de lanzarla al público.
