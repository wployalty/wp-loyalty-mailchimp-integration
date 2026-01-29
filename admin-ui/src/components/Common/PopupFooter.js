import React from 'react';
import {CommonContext, UiLabelContext} from "../../Context";
import Icon from "./Icon";

const PopupFooter = ({show}) => {
    const labels = React.useContext(UiLabelContext);
    const {appState} = React.useContext(CommonContext);


    return (show === "show") && <div
        className={`flex items-center justify-center w-full shadow-card_top  bg-white gap-x-1   text-xs_13_16 font-medium py-2 px-5`}>
        <p className={`p-1 text-extra_light text-xs lg:text-sm font-medium`}>{labels.common.powered_by}</p>
        <Icon icon={"wployalty_logo"}/>
        <p onClick={() => window.open(labels.common.launcher_power_by_url)}
           className={` cursor-pointer text-xs  lg:text-sm font-medium `}
           style={{color: `#5850ec`}}
        >{labels.common.wpl_loyalty_text}</p>
    </div>

};

export default PopupFooter;