# Agente de escaneo en puestos remotos

El agente debe ejecutarse en una computadora Windows de cada red que se quiera relevar.
La pantalla de EA solamente permite utilizarlo cuando la sesión corresponde a `nesrojas`.

## Puestos previstos

- Plana Mayor - Personal
- Plana Mayor - Operaciones
- Dirección - SAF
- Informática

## Instalación

1. Copiar completa esta carpeta `network-manager` al puesto remoto. Debe incluir `node_modules`.
2. Instalar Node.js 20 o superior si el equipo todavía no lo tiene.
3. Ejecutar `start-network-manager.bat`. Windows puede solicitar autorización para permitir Node.js en redes privadas.
4. Abrir `http://127.0.0.1:3000` en ese mismo equipo y comprobar que aparece el tablero.
5. Ingresar a EA como `nesrojas`, abrir el escáner, seleccionar la ubicación y ejecutar el escaneo.
6. Pulsar **Guardar escaneo en el árbol** para enviar los resultados al servidor central.

EA registra por separado la ubicación física y el nombre de la computadora que realizó el
escaneo. El agente propone automáticamente el nombre del puesto; antes de guardar también se
puede elegir otro dispositivo del listado detectado.

El agente detecta automáticamente la interfaz y la subred local de esa computadora. No se debe
configurar una subred ajena al puesto, ya que el objetivo es relevar el segmento conectado al switch local.
