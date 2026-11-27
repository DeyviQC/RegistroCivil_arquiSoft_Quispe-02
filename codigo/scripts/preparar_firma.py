"""Place the municipal stamp on an existing page; never edit the archive original."""
import json
import sys
from io import BytesIO
from pypdf import PdfReader, PdfWriter
from reportlab.pdfgen import canvas
from reportlab.lib.colors import HexColor
source, destination, logo, metadata = sys.argv[1:]
info = json.loads(metadata)
reader = PdfReader(source)
if reader.is_encrypted:
    raise ValueError('PDF cifrado no compatible')
page_index = int(info.get('pagina', 1)) - 1
if page_index < 0 or page_index >= len(reader.pages):
    raise ValueError('La página no existe')
page = reader.pages[page_index]
page.transfer_rotation_to_content()
w, h = float(page.mediabox.width), float(page.mediabox.height)
bw, bh, margin = 270, 94, 18
if w < bw + 2*margin or h < bh + 2*margin:
    raise ValueError('Página demasiado pequeña')
position = info.get('posicion', 'inferior_derecha')
x = margin if position.endswith('izquierda') else w-margin-bw
y = h-margin-bh if position.startswith('superior') else margin
buffer = BytesIO()
c = canvas.Canvas(buffer, pagesize=(w, h))
c.setFillColor(HexColor('#f3f7f5')); c.setStrokeColor(HexColor('#9cafaa'))
c.roundRect(x, y, bw, bh, 5, fill=1, stroke=1)
c.drawImage(logo, x+7, y+17, width=53, height=65, preserveAspectRatio=True, anchor='c', mask='auto')
c.setFillColor(HexColor('#174d3e')); c.setFont('Helvetica-Bold', 8)
c.drawString(x+69, y+77, 'REGISTRO CIVIL MUNICIPAL')
c.setFont('Helvetica', 7)
c.drawString(x+69, y+63, 'Documento preparado para firma digital')
c.drawString(x+69, y+50, 'Firmante y fecha: consultar certificado del PDF')
c.drawString(x+69, y+37, 'Motivo: Atención de solicitud de copia de acta')
c.drawString(x+69, y+24, f"Solicitud N. {info['solicitud']} - {info['fecha']}")
c.setFont('Helvetica', 6)
c.drawString(x+8, y+7, 'La firma se acredita mediante validación digital del archivo completo.')
c.save()
page.merge_page(PdfReader(buffer).pages[0])
writer=PdfWriter()
for current in reader.pages:
    writer.add_page(current)
with open(destination, 'wb') as output:
    writer.write(output)
