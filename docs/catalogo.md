# Catálogo — de onde vieram os dados e o que precisa de revisão

## Fonte

- **Cursos, cargas horárias, público-alvo e prática obrigatória:** planilha
  *"Tabela de valores sugeridos para ser cobrados no EAD"*, aba **SUGESTÃO PARA 2025** (119 linhas).
- **Preço de venda:** coluna **"Valor do mercado"** da mesma aba (a coluna "Valor da Venda", sempre
  R$ 79, é o custo por certificação e **não** aparece na loja).
- **Brigadas de incêndio:** a planilha manda consultar a aba **"NR23 por Estado"**. Usei a linha de
  **São Paulo (IT-17/2019)** para os cursos IT 17 e os valores dos estados que seguem a **NBR 14276**
  para os cursos NBR 14276: Básico R$ 173, Intermediário R$ 196, Avançado R$ 207 ("valor matrícula avulsa").
- **Reciclagens de brigada (6 cursos):** a planilha não traz valor → ficaram **"Sob consulta"**
  (sem compra online; o botão vira "Solicitar proposta").
- **Nomes:** quando a planilha abreviava, usei o nome do *White Label – Catálogo Treinamentos 2026*
  (ex.: "Disposições Gerais e Gerenciamento de Riscos Ocupacionais"). O PPR foi classificado na NR 15
  e as brigadas/PPCI na NR 23, como no catálogo 2026.

O arquivo gerado é `database/data/cursos.json` (script `database/data/build_catalog.py`). O seed importa
esse JSON só uma vez por curso: **edições feitas no painel nunca são sobrescritas**.

## Regras aplicadas

| Regra | Onde |
|---|---|
| "Prática obrigatória: Sim" → modalidade **Semipresencial**; "Não" → **Online** | todos os cursos |
| Texto da coluna "Público Alvo" → seção **Para quem é** (sem edição) | página do curso |
| "(Reciclagem)" / "(Periódico)" no nome → tipo **Periódico / Reciclagem** | filtro "Tipo" |
| Nenhum selo "Mais vendido" nem preço riscado inventado | vêm do painel |
| Destaques da home = os 8 cursos que o protótipo destacava (NR 10, NR 33, CIPA GR1, empilhadeira, NR 20, primeiros socorros, NR 12, NR 1) | editável no painel |

## Correções feitas na planilha

1. **Linhas 28 e 29 (NR 10 SEP):** os nomes estavam trocados na coluna A. O texto de público-alvo e o
   padrão de preço (formação > reciclagem) mostram que a linha 28 (R$ 263) é o SEP de formação e a 29
   (R$ 239) é a reciclagem.
2. **"Direção Preventiva para Froteiros"** → **"Direção Defensiva para Frotistas"** (nome do catálogo 2026).

## Divergências para a Dafnis revisar (planilha 2025 × catálogo 2026)

A loja mostra a carga horária **da planilha**. O catálogo 2026 (versão revisada 07/2026) diz outra coisa nestes casos:

| Curso | Planilha 2025 | Catálogo 2026 |
|---|---|---|
| NR 1 — Disposições Gerais | 4 h | 1 h |
| NR 10 — SEP Reciclagem | 40 h | 20 h |
| NR 11 — Ponte Rolante (iniciação / periódico) | 16 h / 8 h | 8 h / 4 h |
| NR 11 — Transporte e Movimentação de Materiais (reciclagem) | 16 h (R$ 144, mais cara que a formação) | 4 h |
| NR 12 — Máquinas e Equipamentos (geral / reciclagem) | 12 h / 12 h | 8 h / 8 h |
| NR 13 — Caldeiras (periódico) | 40 h + prática | 8 h |
| NR 13 — Unidades de Processo 1, 2 e 3 | 340 h (inclui 300 h de estágio prático) | 40 / 56 / 64 h (iniciação), 8 h (periódico) |
| NR 18 — Indústria da Construção | 2 h + 2 h de prática | 4 h |
| NR 32 — Serviços de Saúde | 4 h | 8 h |
| Brigada NBR 14276 (básico / intermediário / avançado) | 16 / 32 / 56 h | aba NR23: 8 / 16 h (avançado sem carga) |

Também vale revisar: o público-alvo do **NR 10 Básico** fala em "reciclagem anual" (texto da planilha).

Tudo isso se corrige em **Painel › Cursos › Editar**.

## Pendências comerciais (decisão da Dafnis)

- **Parte prática dos cursos semipresenciais:** o preço da planilha é do EAD. A loja informa "Valor por
  participante · parte teórica online" e pede para falar com a equipe sobre a prática. Se o preço
  passar a incluir a prática, ajuste o preço e o texto em Configurações › "Aviso dos cursos com parte prática".
- **Conteúdo programático:** o catálogo PDF não traz as ementas (só um botão). Enquanto não forem
  cadastradas, a página oferece "Solicitar conteúdo programático".
- **Prazo de acesso** na plataforma: não informado; o campo existe no painel e só aparece se preenchido.
