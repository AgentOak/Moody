<?
// .def("ABC", 7)
// .ifdef("ABC")
 echo 'Notch';
// .endif
// .ifdef("SOME_UNDEFINED_CONSTANT")
 echo 'This should never show up in the code';
// .endif
// .ifdef("ABC")
 // .def("BEST_SOFTWARE", "Pancake")
 // .ifdef("BEST_SOFTWARE")
 echo 'Nested ifs are working! :D';
  // .ifdef("TROLL")
   echo 'Not good.';
  // .else
   echo 'Else is working! :D';
 // .endif
// .endif
// Normal comment
echo 'Some Code';
echo /* .constant("ABC") */;
// .undefine("ABC")
// .ifdef("ABC")
 echo 'Trolol';
// .endif
// .ifndef("ABC")
 // .def("ABC", 'Pancake')
 echo /* .constant('ABC') */;
// .endif
// .halt
// .unhandledInstruction
?>
