import React from 'react';
import Navbar from "../navbar/Navbar";
import RouterContainer from "../routes/RouterContainer";
import {HashRouter} from "react-router-dom";

const NavbarContentContainer = (props) => {
    return <div className={`flex flex-col`}>

        {/* ************************ content wrapper ********************************** */}
        <HashRouter>
            <Navbar/>
            <RouterContainer/>
        </HashRouter>

    </div>
};

export default NavbarContentContainer;
