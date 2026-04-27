# 🛡️ Guia de Restauração do Sistema (Cetusg Plus)

Este guia ensina como recuperar o sistema em um computador novo utilizando o arquivo de backup gerado.

## 📋 Pré-requisitos
1. Baixar e instalar o **XAMPP** no novo computador (Caminho padrão recomendado: `C:\xampp`).
2. Ter o arquivo de backup `.zip` em mãos (localizado em `D:\SISTEMA REDE ARRASTAO`).

---

## 🚀 Passo a Passo para Recuperação

### 1. Preparar os Arquivos
1. Extraia o conteúdo do arquivo `.zip` do backup.
2. Você encontrará:
   - Uma pasta chamada `cetusg` (Contendo PHP, fotos, etc).
   - Um arquivo chamado `full_backup_cetusg_... .sql` (Seu banco de dados).
3. Mova a pasta `cetusg` para dentro de `C:\xampp\htdocs\`.

### 2. Preparar o Banco de Dados
1. Abra o **XAMPP Control Panel** e inicie o **Apache** e o **MySQL**.
2. Acesse [http://localhost/phpmyadmin](http://localhost/phpmyadmin) no seu navegador.
3. Clique em **Novo** (New) no menu lateral esquerdo.
4. Nome do banco de dados: `cetusg_plus`. Clique em **Criar**.
5. Selecione o banco `cetusg_plus` que acabou de criar.
6. Clique na aba **Importar** (Import) no topo da página.
7. Clique em **Escolher arquivo** e selecione o arquivo `.sql` que estava no backup.
8. Vá até o final da página e clique em **Importar** (ou Executar).

### 3. Finalizar
1. Acesse o sistema pelo navegador: [http://localhost/cetusg](http://localhost/cetusg).
2. Seus dados, fotos e configurações estarão exatamente como no momento do backup.

---

> [!TIP]
> **Dica de Segurança:** Mantenha sempre o HD Externo (D:) conectado ou faça uma cópia do arquivo de backup para um serviço de nuvem (Google Drive/OneDrive) regularmente para proteção extra contra falha física do computador.
