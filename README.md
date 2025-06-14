# DImage - PHPMX

Ferramenta para manipulação e edição de imagens usando a biblioteca GD.

    composer require phpmx/dimage

## Utilização

Utilize a classe estática **PhpMx\DImage** para criar uma nova imagem

    $dimage = DImage::_color($color, $size); //Cria um objeto de imagem com cor chapada
    $dimage = DImage::_file($path); //Cria um objeto de imagem baseado em um arquivo de seu projeto
    $dimage = DImage::_url($url); //Cria um objeto de imagem baseado em uma URL externa

A classe tenta ao máximo manter o nome do arquivo original. Você pode renomear o arquivo se desejar

    $dimage->rename('novaImagem'); //Renomeia o arquivo para novaImagem

Atualmente, a classe suporta imagens dos tipos **JPEG**,**PNG**,**WEBP** e **BMP** com suporte parcial para **GIF** estático e nenhum suporte para gifs animados.
Uma vez que o objeto DImage tenha sido criado, você pode alterar o tipo do arquivo usando o metodo **convert**

    $dimage->convert('webp'); //Converte o objeto de imagem para WEBP
    $dimage->convert('jpg'); //Converte o objeto de imagem para JPEG
    $dimage->convert('png'); //Converte o objeto de imagem para PNG
    $dimage->convert('bmp'); //Converte o objeto de imagem para BMP
    $dimage->convert('gif'); //Converte o objeto de imagem para GIF

Você pode salvar o objeto de imagem em seu projeto utilizando o metodo **save**

    $dimage->save('storage/image'); //Salva o arquivo dentro da pasta /storage/image/[fileName]

Os metodos da classe podem ser utilizados de forma encadeada livremente

    DImage::_url('https://avatars.githubusercontent.com/u/215534201') //Carrega a logo do PHPMX
        ->convert('webp') //Converte para WEBP
        ->rename('phpmx') //Renomeia para phpmx
        ->save('storage/image'); //Salva em storage/image

## Manipulação

A classe oferece diversos métodos para manipulação de objetos de imagem.

**raito**: Ajusta a imagem para um aspect-ratio

    $dimage->raito(3.4)

**resize**: Redimensiona a imagem respeitando a proporção

    $dimage->resize(300)
    $dimage->resize([300,400])

**resizeFree**: Redimensiona a imagem não respeitando a proporção

    $dimage->resizeFree(300)
    $dimage->resizeFree([300,400])

**rotate**: Rotaciona uma imagem

    $dimage->rotate(90)

**flipH**: Inverte a imagem na horizontal

    $dimage->flipH()

**flipV**: Inverte a imagem na vertical

    $dimage->flipV()

**crop**: Corta uma parte da imagem

    $dimage->crop(300)

**framing**: Enquadra imagem

    $dimage->framing(300)

**filter**: Aplica um filtro GD a imagem

    $dimage->filter(300)

Filtros podem ser encontrados em [imagefilter](https://www.php.net/imagefilter)

**stamp**: Adiciona uma imagem DImage em uma posição da imagem atual

    $dimage->stamp($dimageStamp)

Todos os metodos estão documentados e podem ser encadeados

    DImage::_url('https://avatars.githubusercontent.com/u/215534201') //Carrega a logo do PHPMX
        ->convert('webp') //Converte para WEBP
        ->rotate(45) //Rotaciona a imagemem 45 graus
        ->flipH() //Inverte a imagem horizontalmente
        ->resizeFree([100, 50]) //Redimenciona a imagem distorcendo a proporção
        ->filter(IMG_FILTER_GRAYSCALE) //Aplica filtro de escala cinza
        ->rename('phpmx') //Renomeia para phpmx
        ->save('storage/image'); //Salva em storage/image

## Informações

Para capturar informações da imagem utilize os metodos abaixo

- **getName()**: Retorna o nome da imagem
- **getPath()**: Retorna o caminho da imagem no disco
- **getGd()**: Retorna a imagem GD gerada pela classe
- **getSize()**: Retorna o array de dimensão da imagem
- **getWidth()**: Retorna a largura da imagem
- **getHeight()**: Retorna a altura da imagem
- **getExtension()**: Retorna a extensao da imagem
- **getFileSize()**: Retorna o tamanho do arquivo da imagem
- **getHash()**: Captura o Hash Md5 gerado pelo binario da imagem
- **getBin()**: Retorna o binario da imagem
- **getB64()**: Retorna a imagem codificada em base64

## Considerações

Embora bem otimizada, a edição de imagens requer mais memoria do que normalmente o PHP usa para requisições. Cuidado com edição de muitiplos arquivos simultaneamente ou armazenamento de conteúdo gerado por **getBin** e **getB64**
