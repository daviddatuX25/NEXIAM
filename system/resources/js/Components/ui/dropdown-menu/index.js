import Root from './dropdown-menu.svelte';
import Trigger from './dropdown-menu-trigger.svelte';
import Content from './dropdown-menu-content.svelte';
import Item from './dropdown-menu-item.svelte';
import Separator from './dropdown-menu-separator.svelte';
import Portal from './dropdown-menu-portal.svelte';
import { DropdownMenu as DropdownMenuPrimitive } from 'bits-ui';

const Sub = DropdownMenuPrimitive.Sub;
const SubTrigger = DropdownMenuPrimitive.SubTrigger;
const SubContent = DropdownMenuPrimitive.SubContent;
const Group = DropdownMenuPrimitive.Group;
const GroupHeading = DropdownMenuPrimitive.GroupHeading;
const RadioGroup = DropdownMenuPrimitive.RadioGroup;
const RadioItem = DropdownMenuPrimitive.RadioItem;
const CheckboxItem = DropdownMenuPrimitive.CheckboxItem;

export {
    Root,
    Trigger,
    Content,
    Item,
    Separator,
    Portal,
    Sub,
    SubTrigger,
    SubContent,
    Group,
    GroupHeading,
    RadioGroup,
    RadioItem,
    CheckboxItem,
    //
    Root as DropdownMenu,
    Trigger as DropdownMenuTrigger,
    Content as DropdownMenuContent,
    Item as DropdownMenuItem,
    Separator as DropdownMenuSeparator,
    Portal as DropdownMenuPortal,
    Sub as DropdownMenuSub,
    SubTrigger as DropdownMenuSubTrigger,
    SubContent as DropdownMenuSubContent,
    Group as DropdownMenuGroup,
    GroupHeading as DropdownMenuGroupHeading,
    RadioGroup as DropdownMenuRadioGroup,
    RadioItem as DropdownMenuRadioItem,
    CheckboxItem as DropdownMenuCheckboxItem,
};
