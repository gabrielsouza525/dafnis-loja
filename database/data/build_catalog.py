"""
Gera database/data/cursos.json a partir da planilha "Tabela de valores sugeridos para ser
cobrados no EAD" (aba SUGESTÃO PARA 2025). Rodado uma vez na criação do projeto; o JSON
gerado é versionado e é ele que o seeder importa.

    python database/data/build_catalog.py "C:/caminho/Tabela de valores....xlsx"

Regras (documentadas em docs/catalogo.md):
- preço = coluna "Valor do mercado"; brigadas usam a aba "NR23 por Estado" (SP / NBR 14276);
  reciclagens de brigada ficam sem preço (sob consulta), porque a planilha não informa.
- prática obrigatória "Sim" => modalidade semipresencial; "Não" => online.
- carga horária, público-alvo e informações de prática vêm da planilha, sem acréscimos.
"""
import json
import re
import sys
import unicodedata

import openpyxl

SRC = sys.argv[1] if len(sys.argv) > 1 else None
OUT = __file__.replace('build_catalog.py', 'cursos.json')

# índice da linha (a partir da 1ª linha de dados) => metadados editoriais
# (nr, código, título, categoria, tipo, ícone, curto, preço manual, destaque)
M = {
    0: dict(title='5S — Ferramentas da Qualidade', cat='corp', icon='clipcheck', short='5S'),
    1: dict(title='Análise de Riscos', cat='seg', icon='clipcheck', short='Análise de Riscos'),
    2: dict(title='Atendimento Pré-Hospitalar (Primeiros Socorros) — Básico', cat='ps', icon='aid', short='APH Básico'),
    3: dict(title='Atendimento Pré-Hospitalar (Primeiros Socorros) — Intermediário', cat='ps', icon='aid', short='APH Intermediário'),
    4: dict(title='Atualização Bombeiro Civil — Classe I (NBR 16877)', cat='brig', icon='flame', short='Bombeiro Civil', type='periodico'),
    5: dict(title='Atualização Bombeiro Civil — Classe II (NBR 16877)', cat='brig', icon='flame', short='Bombeiro Civil', type='periodico'),
    6: dict(title='Atualização Bombeiro Civil — Classe III (NBR 16877)', cat='brig', icon='flame', short='Bombeiro Civil', type='periodico'),
    7: dict(title='Avaliação Geral de Cena', cat='ps', icon='aid', short='Avaliação de Cena'),
    8: dict(title='Direção Defensiva para Motoristas de Caminhão', cat='corp', icon='car', short='Direção Defensiva'),
    # "Froteiros" na planilha; o catálogo 2026 chama de "Direção Defensiva para Frotistas".
    9: dict(title='Direção Defensiva para Frotistas', cat='corp', icon='car', short='Direção Defensiva'),
    10: dict(nr=23, title='Formação de Brigada de Incêndio — Avançado (NBR 14276)', cat='brig', icon='flame', price=207),
    11: dict(nr=23, title='Brigada de Incêndio — Avançado — Reciclagem (NBR 14276)', cat='brig', icon='flame', price=None, type='periodico'),
    12: dict(nr=23, title='Formação de Brigada de Incêndio — Básico (NBR 14276)', cat='brig', icon='flame', price=173),
    13: dict(nr=23, title='Brigada de Incêndio — Básico — Reciclagem (NBR 14276)', cat='brig', icon='flame', price=None, type='periodico'),
    14: dict(nr=23, title='Formação de Brigada de Incêndio — Intermediário (NBR 14276)', cat='brig', icon='flame', price=196),
    15: dict(nr=23, title='Brigada de Incêndio — Intermediário — Reciclagem (NBR 14276)', cat='brig', icon='flame', price=None, type='periodico'),
    16: dict(nr=23, title='Formação de Brigada de Incêndio — Avançado (IT 17 de SP)', cat='brig', icon='flame', price=207),
    17: dict(nr=23, title='Brigada de Incêndio — Avançado — Reciclagem (IT 17 de SP)', cat='brig', icon='flame', price=None, type='periodico'),
    18: dict(nr=23, title='Formação de Brigada de Incêndio — Básico (IT 17 de SP)', cat='brig', icon='flame', price=173),
    19: dict(nr=23, title='Brigada de Incêndio — Básico — Reciclagem (IT 17 de SP)', cat='brig', icon='flame', price=None, type='periodico'),
    20: dict(nr=23, title='Formação de Brigada de Incêndio — Intermediário (IT 17 de SP)', cat='brig', icon='flame', price=196),
    21: dict(nr=23, title='Brigada de Incêndio — Intermediário — Reciclagem (IT 17 de SP)', cat='brig', icon='flame', price=None, type='periodico'),
    22: dict(nr=1, title='Disposições Gerais e Gerenciamento de Riscos Ocupacionais', cat='nr', icon='clipboard', featured=8),
    23: dict(nr=10, title='Segurança em Instalações e Serviços com Eletricidade — Básico', cat='nr', icon='bolt', featured=1),
    24: dict(nr=10, title='Segurança em Instalações e Serviços com Eletricidade — Reciclagem', cat='nr', icon='bolt', type='periodico'),
    # Linhas 25 e 26: nomes trocados na coluna A (o público-alvo e o padrão de preço mostram isso).
    25: dict(nr=10, title='Segurança no Sistema Elétrico de Potência (SEP) — Complementar', cat='nr', icon='bolt'),
    26: dict(nr=10, title='Segurança no Sistema Elétrico de Potência (SEP) — Reciclagem', cat='nr', icon='bolt', type='periodico'),
    27: dict(title='Game Quiz 360°', cat='sim', icon='game', short='Quiz 360°'),
    28: dict(title='Jogo de Percepção de Riscos', cat='sim', icon='game', short='Percepção de Riscos'),
    29: dict(title='LGPD — Lei Geral de Proteção de Dados', cat='corp', icon='lock', short='LGPD'),
    30: dict(title='Líder SST — Liderança em Segurança e Saúde no Trabalho', cat='seg', icon='users', short='Líder SST'),
    31: dict(title='LOTO — Bloqueio e Etiquetagem (Lockout e Tagout)', cat='seg', icon='lock', short='LOTO'),
    32: dict(title='Noções Básicas de Anatomia', cat='ps', icon='pulse', short='Anatomia'),
    33: dict(nr=11, title='Segurança no Trabalho com Empilhadeiras — Iniciação', cat='maq', icon='forklift', featured=4),
    34: dict(nr=11, title='Segurança no Trabalho com Empilhadeiras — Periódico', cat='maq', icon='forklift', type='periodico'),
    35: dict(nr=11, title='Segurança no Trabalho com Ponte Rolante — Iniciação', cat='maq', icon='crane'),
    36: dict(nr=11, title='Segurança no Trabalho com Ponte Rolante — Periódico', cat='maq', icon='crane', type='periodico'),
    37: dict(nr=11, title='Segurança no Trabalho com Transpaleteira — Iniciação', cat='maq', icon='forklift'),
    38: dict(nr=11, title='Segurança no Trabalho com Transpaleteira — Periódico', cat='maq', icon='forklift', type='periodico'),
    39: dict(nr=11, title='Transporte, Movimentação, Armazenagem e Manuseio de Materiais — Reciclagem', cat='maq', icon='box', type='periodico'),
    40: dict(nr=11, title='Transporte, Movimentação, Armazenagem e Manuseio de Materiais — Formação', cat='maq', icon='box'),
    41: dict(nr=12, title='Segurança no Trabalho em Máquinas e Equipamentos — Geral', cat='maq', icon='cog', featured=7),
    42: dict(nr=12, title='Segurança no Trabalho em Máquinas e Equipamentos — Reciclagem', cat='maq', icon='cog', type='periodico'),
    43: dict(nr=13, title='Segurança na Operação de Caldeiras — Iniciação', cat='maq', icon='gauge'),
    44: dict(nr=13, title='Segurança na Operação de Caldeiras — Periódico', cat='maq', icon='gauge', type='periodico'),
    45: dict(nr=13, title='Operação de Unidades de Processo 1 (Vasos de Pressão) — Iniciação', cat='maq', icon='gauge'),
    46: dict(nr=13, title='Operação de Unidades de Processo 1 (Vasos de Pressão) — Periódico', cat='maq', icon='gauge', type='periodico'),
    47: dict(nr=13, title='Operação de Unidades de Processo 2 (Vasos de Pressão) — Iniciação', cat='maq', icon='gauge'),
    48: dict(nr=13, title='Operação de Unidades de Processo 2 (Vasos de Pressão) — Periódico', cat='maq', icon='gauge', type='periodico'),
    49: dict(nr=13, title='Operação de Unidades de Processo 3 (Vasos de Pressão) — Iniciação', cat='maq', icon='gauge'),
    50: dict(nr=13, title='Operação de Unidades de Processo 3 (Vasos de Pressão) — Periódico', cat='maq', icon='gauge', type='periodico'),
    51: dict(nr=17, title='Ergonomia', cat='saude', icon='chair'),
    52: dict(nr=17, title='Ergonomia para Operadores de Checkout', cat='saude', icon='chair'),
    53: dict(nr=17, title='Levantamento e Transporte Manual de Cargas', cat='saude', icon='box'),
    54: dict(nr=17, title='Trabalho em Teleatendimento', cat='saude', icon='chair'),
    55: dict(nr=18, title='Segurança e Saúde no Trabalho na Indústria da Construção — Básico', cat='seg', icon='crane'),
    56: dict(nr=20, title='Segurança na Exposição Ocupacional ao Benzeno', cat='nr', icon='drop'),
    57: dict(nr=20, title='Inflamáveis e Combustíveis — Avançado I', cat='nr', icon='drop'),
    58: dict(nr=20, title='Inflamáveis e Combustíveis — Avançado I — Reciclagem', cat='nr', icon='drop', type='periodico'),
    59: dict(nr=20, title='Inflamáveis e Combustíveis — Avançado II', cat='nr', icon='drop'),
    60: dict(nr=20, title='Inflamáveis e Combustíveis — Avançado II — Reciclagem', cat='nr', icon='drop', type='periodico'),
    61: dict(nr=20, title='Inflamáveis e Combustíveis — Básico — Reciclagem', cat='nr', icon='drop', type='periodico'),
    62: dict(nr=20, title='Inflamáveis e Combustíveis — Básico Classe I', cat='nr', icon='drop', featured=5),
    63: dict(nr=20, title='Inflamáveis e Combustíveis — Básico Classe II', cat='nr', icon='drop'),
    64: dict(nr=20, title='Inflamáveis e Combustíveis — Básico Classe III', cat='nr', icon='drop'),
    65: dict(nr=20, title='Inflamáveis e Combustíveis — Específico Classe II', cat='nr', icon='drop'),
    66: dict(nr=20, title='Inflamáveis e Combustíveis — Específico Classe III', cat='nr', icon='drop'),
    67: dict(nr=20, title='Iniciação sobre Inflamáveis e Combustíveis', cat='nr', icon='drop'),
    68: dict(nr=20, title='Iniciação sobre Inflamáveis e Combustíveis — Reciclagem', cat='nr', icon='drop', type='periodico'),
    69: dict(nr=20, title='Inflamáveis e Combustíveis — Intermediário — Reciclagem', cat='nr', icon='drop', type='periodico'),
    70: dict(nr=20, title='Inflamáveis e Combustíveis — Intermediário Classe I', cat='nr', icon='drop'),
    71: dict(nr=20, title='Inflamáveis e Combustíveis — Intermediário Classe II', cat='nr', icon='drop'),
    72: dict(nr=20, title='Inflamáveis e Combustíveis — Intermediário Classe III', cat='nr', icon='drop'),
    73: dict(nr=22, title='CIPAMIN — Comissão Interna de Prevenção de Acidentes na Mineração', cat='seg', icon='hardhat'),
    74: dict(nr=22, title='Game Circulação na Mina', cat='sim', icon='game'),
    75: dict(nr=26, title='Sinalização de Segurança', cat='seg', icon='sign'),
    76: dict(nr=31, title='CIPATR — CIPA do Trabalho Rural', cat='seg', icon='leaf'),
    77: dict(nr=31, code='NR 31.7', title='Defensivos Agrícolas, Aditivos, Adjuvantes e Produtos Afins', cat='seg', icon='leaf'),
    78: dict(nr=32, title='Segurança e Saúde no Trabalho em Serviços de Saúde', cat='saude', icon='hospital'),
    79: dict(nr=33, title='Espaços Confinados — Supervisor de Entrada', cat='nr', icon='box'),
    80: dict(nr=33, title='Espaços Confinados — Supervisor de Entrada — Reciclagem', cat='nr', icon='box', type='periodico'),
    81: dict(nr=33, title='Espaços Confinados — Trabalhador e Vigia', cat='nr', icon='box', featured=2),
    82: dict(nr=33, title='Espaços Confinados — Trabalhador e Vigia — Reciclagem', cat='nr', icon='box', type='periodico'),
    83: dict(nr=34, title='Trabalho a Quente — Iniciação', cat='seg', icon='spark'),
    84: dict(nr=34, title='Trabalho a Quente — Periódico', cat='seg', icon='spark', type='periodico'),
    85: dict(nr=37, title='Segurança e Saúde em Plataformas de Petróleo — Básico', cat='seg', icon='drop'),
    86: dict(nr=5, title='CIPA — Grau de Risco 1', cat='nr', icon='users', featured=3),
    87: dict(nr=5, title='CIPA — Representante Nomeado — Grau de Risco 1', cat='nr', icon='users'),
    88: dict(nr=5, title='CIPA — Grau de Risco 2', cat='nr', icon='users'),
    89: dict(nr=5, title='CIPA — Representante Nomeado — Grau de Risco 2', cat='nr', icon='users'),
    90: dict(nr=5, title='CIPA — Grau de Risco 3', cat='nr', icon='users'),
    91: dict(nr=5, title='CIPA — Representante Nomeado — Grau de Risco 3', cat='nr', icon='users'),
    92: dict(nr=5, title='CIPA — Grau de Risco 4', cat='nr', icon='users'),
    93: dict(nr=5, title='CIPA — Representante Nomeado — Grau de Risco 4', cat='nr', icon='users'),
    94: dict(nr=6, title='EPI e EPC — Equipamentos de Proteção Individual e Coletiva', cat='seg', icon='hardhat'),
    95: dict(title='PCA — Programa de Conservação Auditiva', cat='saude', icon='pulse', short='PCA'),
    # O catálogo 2026 lista o PPR dentro da NR 15.
    96: dict(nr=15, title='PPR — Programa de Proteção Respiratória', cat='saude', icon='pulse'),
    97: dict(title='Prevenção e Combate à COVID-19', cat='saude', icon='hospital', short='COVID-19'),
    98: dict(nr=23, title='Prevenção e Proteção Contra Incêndios', cat='brig', icon='flame'),
    99: dict(title='Primeiros Socorros — Lei Lucas (Lei nº 13.722)', cat='ps', icon='aid', short='Primeiros Socorros', featured=6),
    100: dict(title='Ressuscitação Cardiopulmonar (RCP)', cat='ps', icon='aid', short='RCP'),
    101: dict(title='Simulador — Avaliação Geral da Cena', cat='sim', icon='game', short='Simulador'),
    102: dict(title='Simulador — Combate a Incêndio', cat='sim', icon='game', short='Simulador'),
    103: dict(nr=10, title='Simulador — Desafio dos EPIs (NR 10)', cat='sim', icon='game'),
    104: dict(nr=11, title='Simulador — Desafio dos EPIs (NR 11)', cat='sim', icon='game'),
    105: dict(nr=12, title='Simulador — Desafio dos EPIs (NR 12)', cat='sim', icon='game'),
    106: dict(nr=13, title='Simulador — Desafio dos EPIs (NR 13)', cat='sim', icon='game'),
    107: dict(nr=18, title='Simulador — Desafio dos EPIs (NR 18)', cat='sim', icon='game'),
    108: dict(nr=20, title='Simulador — Desafio dos EPIs (NR 20)', cat='sim', icon='game'),
    109: dict(nr=33, title='Simulador — Desafio dos EPIs (NR 33)', cat='sim', icon='game'),
    110: dict(nr=11, title='Simulador — Empilhadeira', cat='sim', icon='game'),
    111: dict(nr=11, title='Simulador — Ponte Rolante', cat='sim', icon='game'),
    112: dict(nr=11, title='Simulador — Transpaleteira', cat='sim', icon='game'),
    113: dict(title='Simulador — Preenchimento de APR', cat='sim', icon='game', short='APR'),
    114: dict(title='Simulador — Ressuscitação Cardiopulmonar (RCP)', cat='sim', icon='game', short='RCP'),
    115: dict(title='Simulador — Sinalização de Segurança', cat='sim', icon='game', short='Simulador'),
    116: dict(title='Simulador — Técnicas de Abandono de Área', cat='sim', icon='game', short='Simulador'),
    117: dict(title='Simulador LOTO — Guia Prático', cat='sim', icon='game', short='LOTO'),
    118: dict(title='Valas e Escavações', cat='seg', icon='crane', short='Valas e Escavações'),
}

# Sinônimos de busca por NR (termos do dia a dia, não informação do curso).
NR_KEYWORDS = {
    1: 'gro pgr gerenciamento de riscos disposicoes gerais',
    5: 'cipa comissao interna de prevencao de acidentes cipeiro',
    6: 'epi epc equipamento de protecao individual coletiva',
    10: 'eletricidade eletrica eletricista choque',
    11: 'empilhadeira ponte rolante transpaleteira movimentacao de cargas',
    12: 'maquinas equipamentos',
    13: 'caldeira vaso de pressao',
    15: 'insalubridade respirador mascara',
    17: 'ergonomia postura',
    18: 'construcao civil obra',
    20: 'inflamaveis combustiveis',
    22: 'mineracao mina',
    23: 'incendio brigada brigadista extintor bombeiro',
    26: 'sinalizacao placas',
    31: 'rural agricultura agrotoxico',
    32: 'hospital saude',
    33: 'espaco confinado vigia',
    34: 'solda soldagem trabalho a quente',
    37: 'petroleo plataforma offshore',
}


def slugify(text):
    text = unicodedata.normalize('NFD', text)
    text = ''.join(ch for ch in text if unicodedata.category(ch) != 'Mn')
    text = re.sub(r'[^a-zA-Z0-9]+', '-', text.lower()).strip('-')
    return re.sub(r'-{2,}', '-', text)


def clean(text):
    text = (text or '').replace('\r', ' ').replace('\n', ' ').replace('_x000D_', '')
    text = text.replace('\u00a0', ' ').replace('\ufffd', '').replace('\u2002', ' ')
    return re.sub(r'\s{2,}', ' ', text).strip(' .')


def hours_text(text):
    """'Recomendamos 04h' => 'Recomendamos 4 h'; '02 Horas' => '2 h'."""
    text = clean(text)
    if text in ('', '-', '_'):
        return None
    text = re.sub(r'\b0?(\d+)\s*(?:h|horas?|Horas?)\b\.?', r'\1 h', text)
    text = re.sub(r'^(\d+)\s*\(', r'\1 h (', text)   # "8 (Proteção...)" => "8 h (Proteção...)"
    text = text.replace(' h/', ' h; ').replace(' -  ', ' - ')
    text = re.sub(r'([Mm])inimo', r'ínimo', text)
    return text[0].upper() + text[1:]


def main():
    wb = openpyxl.load_workbook(SRC, data_only=True)
    ws = wb.worksheets[0]
    rows = []
    for row in ws.iter_rows(min_row=3, values_only=True):
        if row[0] is None or str(row[0]).strip() == '':
            continue
        rows.append([('' if v is None else str(v)) for v in row[:7]])

    if len(rows) != len(M):
        sys.exit(f'Esperava {len(M)} linhas, encontrei {len(rows)} — a planilha mudou; revise o mapeamento.')

    courses, slugs = [], set()
    for i, r in enumerate(rows):
        meta = M[i]
        name, audience, hours_raw, prat, prat_h, market = r[0], r[1], r[2], r[3], r[4], r[5]
        # 25 <-> 26: os preços/cargas seguem a posição, os nomes estavam invertidos.
        m = re.match(r'\s*(\d+)', hours_raw)
        hours = int(m.group(1)) if m else None
        hours_note = None
        if 'TIPO DE CALDEIRA' in hours_raw.upper():
            hours_note = '+ prática conforme o tipo de caldeira'
        practical = clean(prat).lower().startswith('sim')
        prat_note = None
        pl = clean(prat)
        if practical and ',' in pl:
            prat_note = pl.split(',', 1)[1].strip()
            prat_note = prat_note[0].upper() + prat_note[1:] + '.'
        if 'price' in meta:
            price = meta['price']
        else:
            price = int(float(market)) if re.match(r'^\d+(\.\d+)?$', market.strip()) else None
        nr = meta.get('nr')
        code = meta.get('code') or (f'NR {nr}' if nr else None)
        title = meta['title']
        slug_base = (f'nr-{nr}-' if nr and not title.lower().startswith('simulador') else '') + slugify(title)
        slug = slug_base[:90].rstrip('-')
        n = 2
        while slug in slugs:
            slug = f'{slug_base[:86]}-{n}'
            n += 1
        slugs.add(slug)
        keywords = ' '.join(filter(None, [NR_KEYWORDS.get(nr, ''), slugify(meta.get('short', '')).replace('-', ' ')]))
        courses.append({
            'source_row': i + 3,
            'source_name': clean(name),
            'slug': slug,
            'nr': nr,
            'code': code,
            'title': title,
            'short_title': meta.get('short'),
            'category': meta['cat'],
            'training_type': meta.get('type', 'inicial'),
            'modality': 'semipresencial' if practical else 'online',
            'hours': hours,
            'hours_note': hours_note,
            'practical_required': practical,
            'practical_hours': hours_text(prat_h) if practical else None,
            'practical_note': prat_note,
            'audience': clean(audience) or None,
            'price': price,
            'icon': meta['icon'],
            'featured': meta.get('featured'),
            'keywords': keywords.strip() or None,
        })

    with open(OUT, 'w', encoding='utf-8') as fh:
        json.dump(courses, fh, ensure_ascii=False, indent=1)
    print(f'{len(courses)} cursos gravados em {OUT}')
    no_price = [c['title'] for c in courses if c['price'] is None]
    print('Sem preço (sob consulta):', len(no_price))


if __name__ == '__main__':
    if not SRC:
        sys.exit('Informe o caminho da planilha.')
    main()
