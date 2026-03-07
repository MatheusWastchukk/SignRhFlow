<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Contrato de Admissao</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #222;
            font-size: 12px;
            line-height: 1.5;
        }
        h1 {
            font-size: 20px;
            margin-bottom: 6px;
        }
        .muted {
            color: #666;
            font-size: 11px;
            margin-bottom: 18px;
        }
        .section {
            margin-bottom: 16px;
        }
        .label {
            font-weight: bold;
        }
    </style>
</head>
<body>
<h1>Contrato de Admissao</h1>
<p class="muted">Documento gerado automaticamente pelo SignRhFlow.</p>

<div class="section">
    <p><span class="label">Contrato ID:</span> {{ $contract->id }}</p>
    <p><span class="label">Data de geracao:</span> {{ $generatedAt->format('d/m/Y H:i:s') }}</p>
    <p><span class="label">Metodo de envio:</span> {{ $contract->delivery_method }}</p>
</div>

<div class="section">
    <p><span class="label">Colaborador:</span> {{ $employee->name }}</p>
    <p><span class="label">E-mail:</span> {{ $employee->email }}</p>
    <p><span class="label">Telefone:</span> {{ $employee->phone }}</p>
    <p><span class="label">CPF:</span> {{ $employee->cpf }}</p>
</div>

<div class="section">
    <p>
        Pelo presente instrumento, as partes concordam com os termos de admissao descritos neste documento.
        Esta versao inicial foi criada para suportar o fluxo tecnico de assinatura digital.
    </p>
</div>
</body>
</html>
