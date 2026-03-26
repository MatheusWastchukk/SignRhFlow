<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Contrato Individual de Trabalho</title>
    <style>
        @page {
            margin: 28mm 20mm 24mm 20mm;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #222;
            font-size: 11px;
            line-height: 1.65;
        }
        .header {
            text-align: center;
            border-bottom: 1px solid #bbb;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }
        .title {
            font-size: 17px;
            font-weight: bold;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            margin: 0;
        }
        .subtitle {
            color: #666;
            font-size: 10px;
            margin-top: 6px;
            margin-bottom: 0;
        }
        p {
            margin: 0 0 10px 0;
            text-align: justify;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin: 14px 0 18px 0;
            font-size: 10px;
        }
        .meta-table td {
            border: 1px solid #d6d6d6;
            padding: 7px 9px;
            vertical-align: top;
        }
        .clause-title {
            margin-top: 16px;
            margin-bottom: 8px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .parties {
            margin-bottom: 14px;
        }
        .muted {
            color: #666;
            font-size: 10px;
        }
        .label {
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class="header">
    <p class="title">Contrato Individual de Trabalho</p>
    <p class="subtitle">Instrumento particular para formalizacao de relacao empregaticia</p>
</div>

<p class="parties">
    Pelo presente instrumento particular, de um lado a <span class="label">CONTRATANTE</span>, pessoa juridica de direito privado, doravante denominada apenas <span class="label">EMPREGADORA</span>, e, de outro lado, <span class="label">{{ $employee->name }}</span>, inscrito(a) no CPF sob o n. <span class="label">{{ $employee->cpf }}</span>, e-mail <span class="label">{{ $employee->email }}</span>, telefone <span class="label">{{ $employee->phone }}</span>, doravante denominado(a) <span class="label">EMPREGADO(A)</span>, tem entre si justo e contratado o que segue, observada a legislacao trabalhista brasileira aplicavel.
</p>

<table class="meta-table">
    <tr>
        <td><span class="label">Identificacao do contrato:</span> {{ $contract->id }}</td>
        <td><span class="label">Data de emissao:</span> {{ $generatedAt->format('d/m/Y H:i:s') }}</td>
    </tr>
</table>

<p class="clause-title">Clausula 1 - Do Objeto</p>
<p>
    O presente contrato tem por objeto a prestacao de servicos pessoais, nao eventuais, mediante subordinacao e remuneracao, nos termos da Consolidacao das Leis do Trabalho - CLT, para exercicio de atividades compatíveis com a funcao designada pela EMPREGADORA.
</p>

<p class="clause-title">Clausula 2 - Da Jornada e Das Obrigacoes</p>
<p>
    O(A) EMPREGADO(A) compromete-se a cumprir a jornada estabelecida internamente pela EMPREGADORA, observados os limites legais, bem como a executar suas atividades com diligencia, boa-fe, sigilo profissional e observancia das politicas internas de conduta, seguranca da informacao e protecao de dados.
</p>

<p class="clause-title">Clausula 3 - Da Remuneracao e Beneficios</p>
<p>
    A remuneracao mensal, beneficios, forma de pagamento e eventuais adicionais serao definidos em documento complementar de admissao e/ou politica interna, que integra este contrato para todos os fins, respeitada a legislacao vigente e eventuais instrumentos coletivos aplicaveis.
</p>

<p class="clause-title">Clausula 4 - Da Vigencia e Rescisao</p>
<p>
    O presente contrato entra em vigor na data de assinatura eletrônica pelas partes e vigorara por prazo indeterminado, salvo disposicao diversa em aditivo especifico. A rescisao observara as hipoteses legais e contratuais, inclusive quanto a aviso previo, verbas rescisorias e obrigacoes acessorias.
</p>

<p class="clause-title">Clausula 5 - Da Assinatura Eletronica e Foro</p>
<p>
    As partes reconhecem como valida a assinatura eletrônica realizada por plataforma de certificacao digital integrada ao fluxo da EMPREGADORA, produzindo todos os efeitos juridicos de assinatura presencial. Fica eleito o foro da comarca da sede da EMPREGADORA para dirimir controversias oriundas deste instrumento, com renuncia a qualquer outro, por mais privilegiado que seja.
</p>

<p class="muted">
    Documento gerado automaticamente pelo SignRhFlow para fins de demonstracao do fluxo de formalizacao contratual e assinatura digital.
</p>

</body>
</html>
