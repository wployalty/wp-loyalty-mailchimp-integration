import React from 'react';
import {CommonContext} from "../../Context";

const Icon = ({
                  icon,
                  fontSize = "text-sm 2xl:text-md",
                  fontWeight = "font-medium",
                  color,
                  click,
                  opactity,
                  width = "",
                  show = "",
                  extraStyles = ""
              }) => {
    const {commonState} = React.useContext(CommonContext);
    color = ["", undefined].includes(color) ? commonState.design.colors.theme.primary : color;
    return <i
        onClick={click}
        style={{color: `${color}`}}
        className={`wlr wlrf-${icon} cursor-pointer  ${fontSize} ${show} ${width}
   ${fontWeight} ${extraStyles}
   ${opactity} 
    `}/>

};

export default Icon;