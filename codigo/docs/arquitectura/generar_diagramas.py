from PIL import Image,ImageDraw,ImageFont
from pathlib import Path
import html
W,H=1600,1100
OUT=Path('docs/arquitectura')
GREEN='#3f4d2a'; DARK='#253020'; INK='#273128'; MUTED='#657061'; BG='#f6f5ef'; LINE='#d7ddd0'; PALE='#edf2e6'
FONT='C:/Windows/Fonts/segoeui.ttf'; BOLD='C:/Windows/Fonts/segoeuib.ttf'
class Diagram:
 def __init__(self):
  self.im=Image.new('RGB',(W,H),BG);self.d=ImageDraw.Draw(self.im);self.svg=[f'<svg xmlns="http://www.w3.org/2000/svg" width="{W}" height="{H}" viewBox="0 0 {W} {H}"><rect width="{W}" height="{H}" fill="{BG}"/>']
 def box(self,x,y,w,h,fill='white',stroke=LINE,r=20):
  self.d.rounded_rectangle((x,y,x+w,y+h),radius=r,fill=fill,outline=stroke,width=2)
  self.svg.append(f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="{r}" fill="{fill}" stroke="{stroke}" stroke-width="2"/>')
 def text(self,x,y,t,size=28,color=INK,bold=False,center=False):
  f=ImageFont.truetype(BOLD if bold else FONT,size);width=self.d.textlength(t,font=f)
  self.d.text((x-width/2 if center else x,y),t,font=f,fill=color)
  self.svg.append(f'<text x="{x}" y="{y+size}" font-family="Segoe UI,Arial,sans-serif" font-size="{size}" font-weight="{700 if bold else 400}" fill="{color}" text-anchor="{"middle" if center else "start"}">{html.escape(t)}</text>')
 def arrow(self,x1,y1,x2,y2):
  self.d.line((x1,y1,x2,y2),fill=GREEN,width=4)
  if y2>y1:pts=[(x2,y2),(x2-9,y2-14),(x2+9,y2-14)]
  else:pts=[(x2,y2),(x2-14,y2-9),(x2-14,y2+9)]
  self.d.polygon(pts,fill=GREEN)
  self.svg.append(f'<path d="M{x1} {y1} L{x2} {y2}" stroke="{GREEN}" stroke-width="4"/><polygon points="'+ ' '.join(f'{x},{y}' for x,y in pts)+f'" fill="{GREEN}"/>')
 def header(self,tag,title,sub):
  self.text(80,45,'REGISTRO CIVIL MUNICIPAL  /  '+tag,20,GREEN,True)
  self.text(80,88,title,49,DARK,True)
  self.text(80,158,sub,25,MUTED)
 def save(self,name):
  self.im.save(OUT/(name+'.png'));(OUT/(name+'.svg')).write_text('\n'.join(self.svg)+ '</svg>',encoding='utf-8')

a=Diagram();a.header('ENFOQUE','Arquitectura por capas','Organización actual: MVC de Laravel, servicios y políticas de autorización.')
layers=[
 ('01','PRESENTACIÓN','Vistas Blade + Bootstrap','Formularios, búsqueda, fichas y reportes'),
 ('02','CONTROL DE SOLICITUDES','Controladores + Form Requests + Middleware','Reciben peticiones, validan datos y controlan el acceso'),
 ('03','LÓGICA DE APLICACIÓN','Servicios + Políticas','Registro de actas, numeración, PDF, auditoría y permisos'),
 ('04','PERSISTENCIA','Modelos Eloquent + Laravel Storage','Datos en MySQL y documentos en almacenamiento privado')]
for i,(num,title,tech,desc) in enumerate(layers):
 y=245+i*185
 a.box(180,y,1240,140)
 a.box(204,y+24,90,90,PALE,PALE,14);a.text(249,y+45,num,31,GREEN,True,True)
 a.text(325,y+16,title,24,GREEN,True);a.text(325,y+50,tech,30,INK,True);a.text(325,y+94,desc,23,MUTED)
 if i<3:a.arrow(800,y+145,800,y+178)
a.text(800,1020,'Los servicios utilizan directamente Laravel y Eloquent.',24,MUTED,False,True)
a.save('enfoque-por-capas')

b=Diagram();b.header('ESTILO','Arquitectura monolítica','Una aplicación Laravel reúne las funcionalidades y se despliega como una unidad.')
b.box(70,405,270,195);b.text(205,435,'Personal municipal',25,INK,True,True);b.text(205,483,'Administrador',25,MUTED,False,True);b.text(205,524,'Asistente',25,MUTED,False,True)
b.arrow(342,501,445,501);b.text(393,458,'Web',22,GREEN,True,True)
b.box(450,230,1080,570,'white',GREEN,25)
b.box(480,260,1020,82,GREEN,GREEN,15);b.text(990,278,'UNA APLICACIÓN LARAVEL',32,'white',True,True)
b.text(990,364,'Interfaz Blade · Controladores · Validaciones',25,MUTED,False,True)
for x,y,title,desc in [(495,419,'Usuarios','Cuentas, roles y recuperación'),(1005,419,'Registro de actas','Nacimiento, matrimonio y defunción'),(495,570,'Auditoría','Historial de operaciones'),(1005,570,'Reportes','Resumen, listado y sobres')]:
 b.box(x,y,480,120,PALE,LINE,15);b.text(x+240,y+22,title,29,GREEN,True,True);b.text(x+240,y+70,desc,21,MUTED,False,True)
b.text(990,740,'Autenticación, servicios y políticas compartidos',24,MUTED,False,True)
b.arrow(745,802,745,875);b.arrow(1230,802,1230,875)
b.box(500,881,475,132);b.text(737,901,'MySQL',30,GREEN,True,True);b.text(737,948,'Usuarios, actas y auditoría',23,MUTED,False,True)
b.box(1000,881,475,132);b.text(1237,901,'Almacenamiento privado',29,GREEN,True,True);b.text(1237,948,'PDF originales de las actas',23,MUTED,False,True)
b.text(800,1050,'El acceso a datos y documentos está protegido por la aplicación.',23,MUTED,False,True)
b.save('estilo-monolitico')
print('Creados 2 diagramas en PNG y SVG.')