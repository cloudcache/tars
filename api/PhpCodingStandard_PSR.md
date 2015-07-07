# PHP代码规范-PSR
参考: [PSR](https://github.com/PizzaLiu/PHP-FIG)

##一.基本代码规范
###1.概览
- PHP代码文件必须以 <?php 或 <?= 标签开始；
- PHP代码文件必须以 不带BOM的 UTF-8 编码；
- PHP代码中应该只定义类、函数、常量等声明，或其他会产生 从属效应 的操作（如：生成文件输出以及修改.ini配置文件等），二者只能选其一；
- 命名空间以及类必须符合 PSR 的自动加载规范：PSR-0 或 PSR-4 中的一个；
- 类的命名必须遵循 StudlyCaps 大写开头的驼峰命名规范；
- 类中的常量所有字母都必须`大写`，单词间用下划线分隔；
- 方法名称必须符合 `camelCase` 式的小写开头驼峰命名规范。

###2.文件
####2.1.PHP标签
PHP代码必须使用 <?php ?> 长标签 或 <?= ?> 短输出标签； 一定不可使用其它自定义标签。
####2.2.字符编码
PHP代码必须且只可使用不带BOM的UTF-8编码。
####2.3. 从属效应
一份PHP文件中应该要不就只定义新的声明，如类、函数或常量等不产生从属效应的操作，要不就只有会产生从属效应的逻辑操作，但不该同时具有两者。  
“从属效应”包含却不仅限于：生成输出、直接的 require 或 include、连接外部服务、修改 ini 配置、抛出错误或异常、修改全局或静态变量、读或写文件等。  
以下为从属效应例子代码的`反例`:
```php
<?php
// 从属效应：修改 ini 配置
ini_set('error_reporting', E_ALL);

// 从属效应：引入文件
include "file.php";

// 从属效应：生成输出
echo "<html>\n";

// 声明函数
function foo()
{
    // 函数主体部分
}
```
###3. 命名空间和类
每个类都独立为一个文件，且命名空间至少有一个层次：顶级的组织名称（vendor name）。

类的命名必须 遵循 `StudlyCaps` 大写开头的驼峰命名规范。

PHP 5.3及以后版本的代码必须使用正式的命名空间。  
例如:  
```php
<?php
// PHP 5.3及以后版本的写法
namespace Vendor\Model;

class Foo
{
}
```
###4. 类的常量、属性和方法
此处的“类”指代所有的类、接口以及可复用代码块（traits）
####4.1. 常量
类的常量中所有字母都必须大写，词间以下划线分隔。 参照以下代码：
```php
<?php
namespace Vendor\Model;

class Foo
{
    const VERSION = '1.0';
    const DATE_APPROVED = '2012-06-01';
}
```

####4.2. 属性
类的属性命名可以遵循 大写开头的驼峰式 (`$StudlyCaps`)、小写开头的驼峰式 (`$camelCase`) 又或者是 下划线分隔式 (`$under_score`)，本规范不做强制要求，但无论遵循哪种命名方式，都应该在一定的范围内保持一致。这个范围可以是整个团队、整个包、整个类或整个方法。

####4.3. 方法
方法名称**必须符合** `camelCase()` 式的小写开头驼峰命名规范。  
  

##二.代码风格指南
###1.概述
- 代码必须使用4个空格来进行缩进，而不是用制表符。

- 一行代码的长度不建议有硬限制；软限制必须为120个字符，建议每行代码80个字符或者更少。

- 在命名空间(namespace)的声明下面必须有一行空行，并且在导入(use)的声明下面也必须有一行空行。

- 类(class)的左花括号必须放到其声明下面自成一行，右花括号则必须放到类主体下面自成一行。

- 方法(method)的左花括号必须放到其声明下面自成一行，右花括号则必须放到方法主体的下一行。

- 所有的属性(property)和方法(method) 必须有可见性声明；抽象(abstract)和终结(final)声明必须在可见性声明之前；而静态(static)声明必须在可见性声明之后。

- 在控制结构关键字的后面必须有一个空格；而方法(method)和函数(function)的关键字的后面不可有空格。

- 控制结构的左花括号必须跟其放在同一行，右花括号必须放在该控制结构代码主体的下一行。

- 控制结构的左括号之后不可有空格，右括号之前也不可有空格。

####1.1.示例
```php
<?php
namespace Vendor\Package;

use FooInterface;
use BarClass as Bar;
use OtherVendor\OtherPackage\BazClass;

class Foo extends Bar implements FooInterface
{
    public function sampleFunction($a, $b = null)
    {
        if ($a === $b) {
            bar();
        } elseif ($a > $b) {
            $foo->bar($arg1);
        } else {
            BazClass::bar($arg2, $arg3);
        }
    }

    final public static function bar()
    {
        // 方法主体
    }
}
```

###2. 通则
####2.1 源文件

所有的PHP源文件必须使用Unix LF(换行)作为行结束符。

所有PHP源文件必须以一个空行结束。

纯PHP代码源文件的关闭标签?> 必须省略。

####2.2 行
一行代码的长度不建议超过80个字符；较长的行建议拆分成多个不超过80个字符的子行。
在非空行后面不可有空格。
一行不可多于一个语句。

####2.4. 缩进
代码`必须`使用4个空格，且不可使用制表符来作为缩进。

####2.5. 关键字和 True/False/Null
PHP关键字(keywords)必须使用小写字母。

PHP常量true, false和null 必须使用小写字母。

###3. 命名空间(Namespace)和导入(Use)声明
命名空间(namespace)的声明后面必须有一行空行。

所有的导入(use)声明必须放在命名空间(namespace)声明的下面。

一句声明中，必须只有一个导入(use)关键字。

在导入(use)声明代码块后面必须有一行空行。

示例:
```php
<?php
namespace Vendor\Package;

use FooClass;
use BarClass as Bar;
use OtherVendor\OtherPackage\BazClass;

// ... 其它PHP代码 ...

```


###4. 类(class)，属性(property)和方法(method)
####4.1. 扩展(extend)和实现(implement)
一个类的扩展(extend)和实现(implement)关键词必须和类名(class name)在同一行。

类(class)的左花括号必须放在下面自成一行；右花括号必须放在类(class)主体的后面自成一行。
```php
<?php
namespace Vendor\Package;

use FooClass;
use BarClass as Bar;
use OtherVendor\OtherPackage\BazClass;

class ClassName extends ParentClass implements \ArrayAccess, \Countable
{
    // 常量、属性、方法
}
```

####4.2. 属性(property)
所有的属性(property)都必须声明其可见性。

变量(var)关键字不可用来声明一个属性(property)。

一条语句不可声明多个属性(property)。

属性名(property name) 不推荐用单个下划线作为前缀来表明其保护(protected)或私有(private)的可见性。

一个属性(property)声明看起来应该像下面这样。
```php
<?php
namespace Vendor\Package;

class ClassName
{
    public $foo = null;
}
```

####4.3. 方法(method)

所有的方法(method)都必须声明其可见性。

方法名(method name) 不推荐用单个下划线作为前缀来表明其保护(protected)或私有(private)的可见性。

方法名(method name)在其声明后面不可有空格跟随。其左花括号必须放在下面自成一行，且右花括号必须放在方法主体的下面自成一行。左括号后面不可有空格，且右括号前面也不可有空格。

一个方法(method)声明看来应该像下面这样。 注意括号，逗号，空格和花括号的位置：
```php
<?php
namespace Vendor\Package;

class ClassName
{
    public function fooBarBaz($arg1, &$arg2, $arg3 = [])
    {
        // 方法主体部分
    }
}
```

####4.4. 方法(method)的参数
在参数列表中，逗号之前不可有空格，而逗号之后则必须要有一个空格。

方法(method)中有默认值的参数必须放在参数列表的最后面。

```php
<?php
namespace Vendor\Package;

class ClassName
{
    public function foo($arg1, &$arg2, $arg3 = [])
    {
        // 方法主体部分
    }
}
```

参数列表可以被拆分为多个缩进了一次的子行。如果要拆分成多个子行，参数列表的第一项必须放在下一行，并且每行必须只有一个参数。

当参数列表被拆分成多个子行，右括号和左花括号之间必须又一个空格并且自成一行。
```php
<?php
namespace Vendor\Package;

class ClassName
{
    public function aVeryLongMethodName(
        ClassTypeHint $arg1,
        &$arg2,
        array $arg3 = []
    ) {
        // 方法主体部分
    }
}
```

####4.5. 抽象(abstract)，终结(final)和 静态(static)
当用到抽象(abstract)和终结(final)来做类声明时，它们必须放在可见性声明的前面。

而当用到静态(static)来做类声明时，则必须放在可见性声明的后面。
```php
<?php
namespace Vendor\Package;

abstract class ClassName
{
    protected static $foo;

    abstract protected function zim();

    final public static function bar()
    {
        // 方法主体部分
    }
}
```
####4.6. 调用方法和函数
调用一个方法或函数时，在方法名或者函数名和左括号之间不可有空格，左括号之后不可有空格，右括号之前也不可有空格。参数列表中，逗号之前不可有空格，逗号之后则必须有一个空格。
```php
<?php
bar();
$foo->bar($arg1);
Foo::bar($arg2, $arg3);
```

参数列表可以被拆分成多个缩进了一次的子行。如果拆分成子行，列表中的第一项必须放在下一行，并且每一行必须只能有一个参数。

```php
<?php
$foo->bar(
    $longArgument,
    $longerArgument,
    $muchLongerArgument
);
```

###5. 控制结构
- 控制结构的关键词之后必须有一个空格。
- 控制结构的左括号之后不可有空格。
- 控制结构的右括号之前不可有空格。
- 控制结构的右括号和左花括号之间必须有一个空格。
- 控制结构的代码主体必须进行一次缩进。
- 控制结构的右花括号必须主体的下一行。
每个控制结构的代码主体必须被括在花括号里。这样可是使代码看上去更加标准化，并且加入新代码的时候还可以因此而减少引入错误的可能性。

####5.1. if，elseif，else
下面是一个if条件控制结构的示例，注意其中括号，空格和花括号的位置。同时注意else和elseif要和前一个条件控制结构的右花括号在同一行。
```php
<?php
if ($expr1) {
    // if body
} elseif ($expr2) {
    // elseif body
} else {
    // else body;
}
```
推荐用elseif来替代else if，以保持所有的条件控制关键字看起来像是一个单词。

####5.2. switch，case
下面是一个switch条件控制结构的示例，注意其中括号，空格和花括号的位置。case语句必须要缩进一级，而break关键字（或其他中止关键字）必须和case结构的代码主体在同一个缩进层级。如果一个有主体代码的case结构故意的继续向下执行则必须要有一个类似于// no break的注释。

```php
<?php
switch ($expr) {
    case 0:
        echo 'First case, with a break';
        break;
    case 1:
        echo 'Second case, which falls through';
        // no break
    case 2:
    case 3:
    case 4:
        echo 'Third case, return instead of break';
        return;
    default:
        echo 'Default case';
        break;
}
```

####5.3. while，do while
下面是一个while循环控制结构的示例，注意其中括号，空格和花括号的位置。
```php
<?php
while ($expr) {
    // structure body
}
```

下面是一个do while循环控制结构的示例，注意其中括号，空格和花括号的位置。
```php
<?php
do {
    // structure body;
} while ($expr);
```

####5.4. for
下面是一个for循环控制结构的示例，注意其中括号，空格和花括号的位置。
```php
<?php
for ($i = 0; $i < 10; $i++) {
    // for body
}
```

####5.5. foreach
下面是一个foreach循环控制结构的示例，注意其中括号，空格和花括号的位置。
```php
<?php
foreach ($iterable as $key => $value) {
    // foreach body
}
```

####5.6. try, catch
下面是一个try catch异常处理控制结构的示例，注意其中括号，空格和花括号的位置。

```php
<?php
try {
    // try body
} catch (FirstExceptionType $e) {
    // catch body
} catch (OtherExceptionType $e) {
    // catch body
}
```

###6. 闭包
声明闭包时所用的function关键字之后必须要有一个空格，而use关键字的前后都要有一个空格。

闭包的左花括号必须跟其在同一行，而右花括号必须在闭包主体的下一行。

闭包的参数列表和变量列表的左括号后面不可有空格，右括号的前面也不可有空格。

闭包的参数列表和变量列表中逗号前面不可有空格，而逗号后面则必须有空格。

闭包的参数列表中带默认值的参数必须放在参数列表的结尾部分。

下面是一个闭包的示例。注意括号，空格和花括号的位置。

```php
<?php
$closureWithArgs = function ($arg1, $arg2) {
    // body
};

$closureWithArgsAndVars = function ($arg1, $arg2) use ($var1, $var2) {
    // body
};
```

参数列表和变量列表可以被拆分成多个缩进了一级的子行。如果要拆分成多个子行，列表中的第一项必须放在下一行，并且每一行必须只放一个参数或变量。

当列表（不管是参数还是变量）最终被拆分成多个子行，右括号和左花括号之间必须要有一个空格并且自成一行。

下面是一个参数列表和变量列表被拆分成多个子行的示例。

```php
<?php
$longArgs_noVars = function (
    $longArgument,
    $longerArgument,
    $muchLongerArgument
) {
   // body
};

$noArgs_longVars = function () use (
    $longVar1,
    $longerVar2,
    $muchLongerVar3
) {
   // body
};

$longArgs_longVars = function (
    $longArgument,
    $longerArgument,
    $muchLongerArgument
) use (
    $longVar1,
    $longerVar2,
    $muchLongerVar3
) {
   // body
};

$longArgs_shortVars = function (
    $longArgument,
    $longerArgument,
    $muchLongerArgument
) use ($var1) {
   // body
};

$shortArgs_longVars = function ($arg) use (
    $longVar1,
    $longerVar2,
    $muchLongerVar3
) {
   // body
};
```

把闭包作为一个参数在函数或者方法中调用时，依然要遵守上述规则。

```php
<?php
$foo->bar(
    $arg1,
    function ($arg2) use ($var1) {
        // body
    },
    $arg3
);
```
