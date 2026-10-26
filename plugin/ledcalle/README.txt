LEDCalle – Publicidad en pantallas LED (plugin WordPress)

INSTALACIÓN
1. Plugins > Añadir nuevo > Subir plugin > ledcalle.zip > Activar.
2. Crea una página "Contratar publicidad" y pon el shortcode:  [ledcalle]
3. Crea otra página "Backoffice LED" (privada o sin enlazar) con:  [ledcalle_admin]
   Solo entran usuarios con el rol "Propietario LED" o administradores (se loguean con su usuario de WordPress).
   Excluye esta página de la caché. No pongas los dos shortcodes en la misma página.
4. Entra en el backoffice > Pantallas > "+ Añadir pantalla".

PAGOS
La reserva se guarda como "Pendiente de pago" y se avisa por email. El propietario la marca como pagada en el backoffice.
Para cobrar online, engancha tu pasarela con el filtro (devuelve la URL de pago y el cliente es redirigido):
  add_filter('ledcalle_checkout_url', function($url,$booking_id,$booking){ return 'https://...'; },10,3);
También existe la acción ledcalle_booking_created.

OTROS
- Fuentes de Google (RGPD: desactivadas por defecto):  add_filter('ledcalle_google_fonts','__return_true');
- Separación del menú fijo del tema:  #ledcalle-root{--ledc-top:70px}
- Los vídeos de máx. 20 s se validan solo en el navegador; el servidor limita tipo (JPG/PNG/MP4) y tamaño (30 MB).
- Datos: tipos de contenido ledc_screen y ledc_booking. Archivos en wp-content/uploads/ledcalle/.
- Precio (45 €/mes) y horario por defecto (6–23 h) están en el texto de assets/app.js y en el alta de pantallas.
